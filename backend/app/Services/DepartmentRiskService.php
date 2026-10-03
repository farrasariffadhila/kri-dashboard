<?php

namespace App\Services;

use App\Models\AuditIssue;
use App\Models\DataLoad;
use App\Models\Department;
use App\Models\Incident;
use App\Models\KriDispute;
use App\Models\PhishingTest;
use App\Models\Server;
use App\Models\ServerComplianceSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Collects KRI metrics from the database and scores them.
 *
 * The reporting month uses the live tables (servers inventory, current audit status),
 * exactly as the PRD defines the KRIs. Earlier months are rebuilt from
 * server_compliance_snapshots and the audit lifecycle dates.
 *
 * Accepted KRI disputes replace the source figure for the reporting month; the source
 * figure is reported next to it so the correction is never silent.
 */
class DepartmentRiskService
{
    private const TOP_OS_LIMIT = 5;

    public function __construct(private readonly RiskScorer $scorer)
    {
    }

    public function reportingMonth(): string
    {
        $month = (string) config('kri.reporting_month');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException("KRI_REPORTING_MONTH must be YYYY-MM, got [{$month}].");
        }

        return $month;
    }

    /** Last day of the reporting month; "overdue" is measured against this date. */
    public function asOf(): CarbonImmutable
    {
        return $this->monthEnd($this->reportingMonth());
    }

    /**
     * Lineage per KRI: the source system and owner (config) plus the latest import of each table it reads.
     */
    public function dataSources(): array
    {
        $latest = DataLoad::query()
            ->whereIn('id', DataLoad::query()->selectRaw('MAX(id)')->groupBy('table_name'))
            ->get()
            ->keyBy('table_name');

        $sources = [];
        foreach ($this->scorer->kris() as $kri) {
            $source = config("kri.sources.{$kri}");
            $sources[$kri] = [
                'system' => $source['system'],
                'owner' => $source['owner'],
                'cadence' => $source['cadence'],
                'simulated' => (bool) config('kri.sources_simulated'),
                'loads' => array_map(function (string $table) use ($latest) {
                    $load = $latest->get($table);

                    return [
                        'table' => $table,
                        'source_file' => $load?->source_file,
                        'row_count' => $load?->row_count,
                        'checksum' => $load?->checksum,
                        'loaded_at' => $load?->loaded_at?->toIso8601String(),
                    ];
                }, $source['tables']),
            ];
        }

        return $sources;
    }

    /** Every department with its KRIs and composite score, highest risk first, plus bank-wide totals. */
    public function summary(): array
    {
        $month = $this->reportingMonth();
        $previousMonth = $this->shiftMonth($month, -1);

        $current = $this->currentMetrics($month);
        $previous = $this->historicalMetrics($previousMonth);

        $departments = Department::query()->orderBy('name')->get()
            ->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                ...$this->scored($current[$d->id] ?? $this->emptyMetrics()),
                'previous_composite_score' => $this->scored($previous[$d->id] ?? $this->emptyMetrics(), detailed: false)['composite_score'],
            ])
            ->sortByDesc(fn (array $row) => $row['composite_score'] ?? -1)
            ->values();

        return [
            'reporting_month' => $month,
            'previous_month' => $previousMonth,
            'as_of' => $this->monthEnd($month)->toDateString(),
            'methodology' => $this->scorer->methodology(),
            'data_sources' => $this->dataSources(),
            'totals' => [
                'current' => $this->scoredTotals($this->currentTotals($month, $current)),
                'previous' => $this->scoredTotals($this->historicalTotals($previousMonth)),
            ],
            'data' => $departments,
        ];
    }

    /** Monthly composite scores for one department, oldest first, ending at the reporting month. */
    public function history(Department $department): array
    {
        $reportingMonth = $this->reportingMonth();
        $months = $this->monthRange($reportingMonth, (int) config('kri.history_months'));

        $points = [];
        foreach (array_slice($months, 0, -1) as $month) {
            $metrics = $this->historicalMetrics($month, $department->id)[$department->id] ?? $this->emptyMetrics();
            $points[] = ['month' => $month, ...$this->scored($metrics, detailed: false)];
        }

        // The last point reuses the summary calculation so the trendline always ends on the heatmap score.
        $current = $this->currentMetrics($reportingMonth, $department->id)[$department->id] ?? $this->emptyMetrics();
        $points[] = ['month' => $reportingMonth, ...$this->scored($current, detailed: false)];

        return [
            'reporting_month' => $reportingMonth,
            'department' => ['id' => $department->id, 'name' => $department->name],
            'data' => $points,
        ];
    }

    /**
     * Raw KRI values plus drill-down details for the reporting month, keyed by department id.
     *
     * @return array<int, array<string, array>>
     */
    private function currentMetrics(string $month, ?int $departmentId = null, bool $applyDisputes = true): array
    {
        $asOf = $this->monthEnd($month);
        $scope = fn (Builder $query) => $query->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        $servers = $scope(Server::query())
            ->groupBy('department_id')
            ->select('department_id', DB::raw('COUNT(*) AS total'), DB::raw('SUM(CASE WHEN is_compliant = 0 THEN 1 ELSE 0 END) AS non_compliant'))
            ->get()
            ->keyBy('department_id');

        $nonCompliantByOs = $scope(Server::query()->nonCompliant())
            ->groupBy('department_id', 'os_version')
            ->select('department_id', 'os_version', DB::raw('COUNT(*) AS servers'))
            ->orderByDesc('servers')
            ->orderBy('os_version')
            ->get()
            ->groupBy('department_id');

        $openHighAudits = $scope(AuditIssue::query()->open()->highRisk())
            ->orderBy('due_date')
            ->get(['id', 'department_id', 'title', 'auditor', 'identified_date', 'due_date'])
            ->groupBy('department_id');

        $incidents = $this->uptimeByDepartment($scope(Incident::query()->forMonth($month)));
        $phishing = $this->phishingByDepartment($scope(PhishingTest::query()->forMonth($month)));

        $metrics = [];
        foreach ($this->departmentIds($departmentId) as $id) {
            $srv = $servers->get($id);
            $audits = $openHighAudits->get($id, collect());
            $inc = $incidents->get($id);
            $phish = $phishing->get($id);

            $metrics[$id] = [
                'unpatched_servers' => [
                    'value' => $srv ? $this->percentage($srv->non_compliant, $srv->total) : null,
                    'non_compliant' => (int) ($srv->non_compliant ?? 0),
                    'total_servers' => (int) ($srv->total ?? 0),
                    'top_non_compliant_os' => $nonCompliantByOs->get($id, collect())
                        ->take(self::TOP_OS_LIMIT)
                        ->map(fn ($row) => ['os_version' => $row->os_version, 'servers' => (int) $row->servers])
                        ->values(),
                ],
                'open_high_audits' => [
                    'value' => $audits->count(),
                    'overdue' => $audits->filter(fn (AuditIssue $a) => $a->due_date->lt($asOf))->count(),
                    'findings' => $audits->map(fn (AuditIssue $a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'auditor' => $a->auditor,
                        'identified_date' => $a->identified_date?->toDateString(),
                        'due_date' => $a->due_date->toDateString(),
                        'days_overdue' => $this->daysOverdue($a->due_date, $asOf),
                    ])->values(),
                ],
                'uptime' => [
                    'value' => $inc ? round((float) $inc->avg_uptime, 2) : null,
                    'total_downtime_minutes' => (int) ($inc->downtime ?? 0),
                    'applications' => (int) ($inc->applications ?? 0),
                ],
                'phishing_failure' => [
                    'value' => $phish ? $this->percentage($phish->failed, $phish->tested) : null,
                    'total_failed' => (int) ($phish->failed ?? 0),
                    'total_tested' => (int) ($phish->tested ?? 0),
                ],
            ];
        }

        return $applyDisputes ? $this->applyDisputes($metrics, $month, $departmentId) : $metrics;
    }

    /**
     * Replace source figures with accepted corrections (latest decision wins) and flag open disputes.
     */
    private function applyDisputes(array $metrics, string $month, ?int $departmentId): array
    {
        $disputes = KriDispute::query()
            ->forMonth($month)
            ->whereIn('status', [KriDispute::STATUS_ACCEPTED, KriDispute::STATUS_PENDING])
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get();

        foreach ($disputes as $dispute) {
            if (! isset($metrics[$dispute->department_id][$dispute->kri])) {
                continue;
            }
            $metric = &$metrics[$dispute->department_id][$dispute->kri];

            if ($dispute->isPending()) {
                $metric['open_dispute_id'] = $dispute->id;
                continue;
            }

            $sourceValue = $metric['correction']['source_value'] ?? $metric['value'];
            $sourceFigure = $metric['correction']['source_figure'] ?? $this->figure($dispute->kri, $metric);
            $corrected = $dispute->corrected_value;

            if ($dispute->kri === 'unpatched_servers') {
                $metric['non_compliant'] = (int) $corrected;
                $metric['value'] = $this->percentage((int) $corrected, $metric['total_servers']);
            } elseif ($dispute->kri === 'phishing_failure') {
                $metric['total_failed'] = (int) $corrected;
                $metric['value'] = $this->percentage((int) $corrected, $metric['total_tested']);
            } else {
                $metric['value'] = round($corrected, 2);
            }

            $metric['correction'] = [
                'dispute_id' => $dispute->id,
                'source_value' => $sourceValue,
                'source_figure' => $sourceFigure,
                'reviewed_at' => $dispute->reviewed_at?->toIso8601String(),
            ];
        }
        unset($metric);

        return $metrics;
    }

    /** The figure a dispute is expressed in: server count, employee count, or uptime percentage. */
    private function figure(string $kri, array $metric): int|float|null
    {
        return match ($kri) {
            'unpatched_servers' => $metric['non_compliant'],
            'phishing_failure' => $metric['total_failed'],
            'uptime' => $metric['value'],
        };
    }

    /**
     * Source and current figures for one disputable KRI of a department in the reporting month,
     * plus the valid range a corrected figure must fall in.
     *
     * @return array{source: int|float|null, current: int|float|null, max: int|float, integer: bool}
     */
    public function disputeBaseline(int $departmentId, string $kri): array
    {
        $month = $this->reportingMonth();
        $source = $this->currentMetrics($month, $departmentId, applyDisputes: false)[$departmentId][$kri];
        $current = $this->currentMetrics($month, $departmentId)[$departmentId][$kri];

        return [
            'source' => $this->figure($kri, $source),
            'current' => $this->figure($kri, $current),
            'max' => match ($kri) {
                'unpatched_servers' => $source['total_servers'],
                'phishing_failure' => $source['total_tested'],
                'uptime' => 100,
            },
            'integer' => $kri !== 'uptime',
        ];
    }

    /**
     * KRI values for a past month, rebuilt from snapshots and audit lifecycle dates, keyed by department id.
     *
     * @return array<int, array<string, array{value: int|float|null}>>
     */
    private function historicalMetrics(string $month, ?int $departmentId = null): array
    {
        $scope = fn (Builder $query) => $query->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        $snapshots = $scope(ServerComplianceSnapshot::query()->forMonth($month))->get()->keyBy('department_id');

        $openHigh = $scope(AuditIssue::query()->highRisk()->openAsOf($this->monthEnd($month)->toDateString()))
            ->groupBy('department_id')
            ->select('department_id', DB::raw('COUNT(*) AS open_high'))
            ->pluck('open_high', 'department_id');

        $incidents = $this->uptimeByDepartment($scope(Incident::query()->forMonth($month)));
        $phishing = $this->phishingByDepartment($scope(PhishingTest::query()->forMonth($month)));

        $metrics = [];
        foreach ($this->departmentIds($departmentId) as $id) {
            $snapshot = $snapshots->get($id);
            $inc = $incidents->get($id);
            $phish = $phishing->get($id);

            $metrics[$id] = [
                'unpatched_servers' => ['value' => $snapshot
                    ? $this->percentage($snapshot->non_compliant, $snapshot->total_servers)
                    : null],
                'open_high_audits' => ['value' => (int) ($openHigh[$id] ?? 0)],
                'uptime' => ['value' => $inc ? round((float) $inc->avg_uptime, 2) : null],
                'phishing_failure' => ['value' => $phish ? $this->percentage($phish->failed, $phish->tested) : null],
            ];
        }

        return $metrics;
    }

    /**
     * Bank-wide KRI values for the reporting month (live tables), including accepted corrections
     * from the per-department metrics so the bank total always agrees with the departments.
     */
    private function currentTotals(string $month, array $departmentMetrics): array
    {
        $servers = Server::query()
            ->select(DB::raw('COUNT(*) AS total'), DB::raw('SUM(CASE WHEN is_compliant = 0 THEN 1 ELSE 0 END) AS non_compliant'))
            ->first();
        $openHigh = AuditIssue::query()->open()->highRisk();

        $totals = $this->totals(
            nonCompliant: (int) $servers->non_compliant,
            totalServers: (int) $servers->total,
            openHigh: (clone $openHigh)->count(),
            overdue: (clone $openHigh)->whereDate('due_date', '<', $this->monthEnd($month)->toDateString())->count(),
            month: $month,
        );

        foreach ($departmentMetrics as $metrics) {
            if ($c = $metrics['unpatched_servers']['correction'] ?? null) {
                $totals['unpatched_servers']['non_compliant'] += $metrics['unpatched_servers']['non_compliant'] - $c['source_figure'];
            }
            if ($c = $metrics['phishing_failure']['correction'] ?? null) {
                $totals['phishing_failure']['total_failed'] += $metrics['phishing_failure']['total_failed'] - $c['source_figure'];
            }
            if (($c = $metrics['uptime']['correction'] ?? null) && $totals['uptime']['applications'] > 0) {
                // The bank figure is the mean over all applications, so weigh the change by the department's share.
                $totals['uptime']['value'] = round(
                    $totals['uptime']['value'] + ($metrics['uptime']['value'] - $c['source_figure']) * $metrics['uptime']['applications'] / $totals['uptime']['applications'],
                    2,
                );
            }
        }
        $totals['unpatched_servers']['value'] = $this->percentage($totals['unpatched_servers']['non_compliant'], $totals['unpatched_servers']['total_servers']);
        $totals['phishing_failure']['value'] = $this->percentage($totals['phishing_failure']['total_failed'], $totals['phishing_failure']['total_tested']);

        return $totals;
    }

    /** Bank-wide KRI values for a past month (snapshots and audit lifecycle dates). */
    private function historicalTotals(string $month): array
    {
        $snapshot = ServerComplianceSnapshot::query()->forMonth($month)
            ->select(DB::raw('SUM(total_servers) AS total'), DB::raw('SUM(non_compliant) AS non_compliant'))
            ->first();

        return $this->totals(
            nonCompliant: (int) $snapshot->non_compliant,
            totalServers: (int) $snapshot->total,
            openHigh: AuditIssue::query()->highRisk()->openAsOf($this->monthEnd($month)->toDateString())->count(),
            overdue: null,
            month: $month,
        );
    }

    private function totals(int $nonCompliant, int $totalServers, int $openHigh, ?int $overdue, string $month): array
    {
        $uptime = Incident::query()->forMonth($month)
            ->select(DB::raw('AVG(actual_uptime_pct) AS avg_uptime'), DB::raw('COUNT(*) AS applications'))
            ->first();
        $phishing = PhishingTest::query()->forMonth($month)
            ->select(DB::raw('SUM(total_employees_tested) AS tested'), DB::raw('SUM(total_failed) AS failed'))
            ->first();

        return [
            'month' => $month,
            'unpatched_servers' => [
                'value' => $this->percentage($nonCompliant, $totalServers),
                'non_compliant' => $nonCompliant,
                'total_servers' => $totalServers,
            ],
            'open_high_audits' => ['value' => $openHigh, 'overdue' => $overdue],
            'uptime' => [
                'value' => $uptime->avg_uptime === null ? null : round((float) $uptime->avg_uptime, 2),
                'applications' => (int) $uptime->applications,
            ],
            'phishing_failure' => [
                'value' => $this->percentage($phishing->failed, $phishing->tested),
                'total_failed' => (int) $phishing->failed,
                'total_tested' => (int) $phishing->tested,
            ],
        ];
    }

    /**
     * Bank-wide KRIs get a score and rating using the department thresholds. Only rates (percentages)
     * are comparable at bank level; the open-audit count is a sum over departments, so it is not rated.
     */
    private function scoredTotals(array $totals): array
    {
        foreach ($this->scorer->kris() as $kri) {
            $score = $kri === 'open_high_audits' ? null : $this->scorer->score($kri, $totals[$kri]['value']);
            $totals[$kri]['score'] = $score === null ? null : round($score, 1);
            $totals[$kri]['rating'] = $this->scorer->rating($score === null ? null : round($score, 1));
        }

        return $totals;
    }

    /** Attach score and weight to every KRI, then the composite score and rating. */
    private function scored(array $metrics, bool $detailed = true): array
    {
        $kris = [];
        $scores = [];

        foreach ($this->scorer->kris() as $kri) {
            $metric = $metrics[$kri] ?? ['value' => null];
            $score = $this->scorer->score($kri, $metric['value']);
            $scores[$kri] = $score;

            $kris[$kri] = [
                'value' => $metric['value'],
                'score' => $score === null ? null : round($score, 1),
                'weight' => $this->scorer->weight($kri),
                ...($detailed ? array_diff_key($metric, ['value' => true]) : []),
            ];
        }

        $composite = $this->scorer->composite($scores);
        $composite = $composite === null ? null : round($composite, 1);

        return [
            'composite_score' => $composite,
            'rating' => $this->scorer->rating($composite),
            'kris' => $kris,
        ];
    }

    private function uptimeByDepartment(Builder $incidents)
    {
        return $incidents->groupBy('department_id')
            ->select('department_id', DB::raw('AVG(actual_uptime_pct) AS avg_uptime'), DB::raw('SUM(total_downtime_minutes) AS downtime'), DB::raw('COUNT(*) AS applications'))
            ->get()
            ->keyBy('department_id');
    }

    private function phishingByDepartment(Builder $phishing)
    {
        return $phishing->groupBy('department_id')
            ->select('department_id', DB::raw('SUM(total_employees_tested) AS tested'), DB::raw('SUM(total_failed) AS failed'))
            ->get()
            ->keyBy('department_id');
    }

    /** @return list<int> */
    private function departmentIds(?int $departmentId): array
    {
        return $departmentId ? [$departmentId] : Department::query()->pluck('id')->all();
    }

    private function emptyMetrics(): array
    {
        return array_fill_keys($this->scorer->kris(), ['value' => null]);
    }

    public function daysOverdue(CarbonImmutable|\DateTimeInterface $dueDate, CarbonImmutable $asOf): int
    {
        return max(0, (int) CarbonImmutable::instance($dueDate)->diffInDays($asOf, false));
    }

    private function percentage(int|string|null $part, int|string|null $total): ?float
    {
        return (int) $total > 0 ? round(100 * (int) $part / (int) $total, 2) : null;
    }

    private function monthEnd(string $month): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $month)->endOfMonth()->startOfDay();
    }

    private function shiftMonth(string $month, int $months): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $month)->addMonthsNoOverflow($months)->format('Y-m');
    }

    /** @return list<string> Months in YYYY-MM, oldest first, ending at $endMonth. */
    private function monthRange(string $endMonth, int $count): array
    {
        return array_map(fn (int $back) => $this->shiftMonth($endMonth, -$back), range($count - 1, 0));
    }
}
