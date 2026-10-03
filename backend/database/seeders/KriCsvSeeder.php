<?php

namespace Database\Seeders;

use App\Models\DataLoad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Services\AttachmentStore;
use RuntimeException;

/**
 * Imports the synthetic CSVs produced by data-generator/generate.py.
 *
 * Source folder comes from config('kri.csv_path') (env KRI_CSV_PATH, default ../data-generator/output).
 * Existing KRI data is truncated first, so the seeder is safe to re-run. Every import is recorded
 * in data_loads (file, row count, SHA-256) so the dashboard can show where its numbers came from.
 */
class KriCsvSeeder extends Seeder
{
    /** Parent tables first; truncation runs in reverse. */
    private const TABLES = [
        'departments',
        'servers',
        'audit_issues',
        'incidents',
        'phishing_tests',
        'server_compliance_snapshots',
    ];

    /** Workflow data that points at imported rows; reset together with them. */
    private const DEPENDENT_TABLES = ['attachments', 'remediation_submissions', 'activity_logs'];

    private const CHUNK_SIZE = 1000;

    public function run(): void
    {
        $path = rtrim(config('kri.csv_path'), '/');

        foreach (self::TABLES as $table) {
            if (! is_readable("{$path}/{$table}.csv")) {
                throw new RuntimeException("CSV not found: {$path}/{$table}.csv. Run data-generator/generate.py first.");
            }
        }

        Schema::disableForeignKeyConstraints();
        foreach ([...self::DEPENDENT_TABLES, ...array_reverse(self::TABLES)] as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
        Storage::disk(AttachmentStore::DISK)->deleteDirectory(AttachmentStore::DIRECTORY);

        foreach (self::TABLES as $table) {
            $file = "{$path}/{$table}.csv";
            $count = $this->import($table, $file);

            DataLoad::create([
                'table_name' => $table,
                'source_file' => basename($file),
                'row_count' => $count,
                'checksum' => hash_file('sha256', $file),
                'loaded_at' => now(),
            ]);

            $this->command?->info(sprintf('%-28s %6d rows', $table, $count));
        }
    }

    private function import(string $table, string $file): int
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle, escape: '');
        $now = now();
        $batch = [];
        $count = 0;

        DB::transaction(function () use ($handle, $header, $table, $now, &$batch, &$count) {
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                // Empty CSV cells (e.g. closed_date of an open issue) become NULL.
                $record = array_map(fn ($v) => $v === '' ? null : $v, array_combine($header, $row));
                $batch[] = $record + ['created_at' => $now, 'updated_at' => $now];

                if (count($batch) === self::CHUNK_SIZE) {
                    DB::table($table)->insert($batch);
                    $count += count($batch);
                    $batch = [];
                }
            }

            if ($batch) {
                DB::table($table)->insert($batch);
                $count += count($batch);
            }
        });

        fclose($handle);

        return $count;
    }
}
