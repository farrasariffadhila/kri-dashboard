<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One import of a source extract into a KRI table. */
class DataLoad extends Model
{
    protected $fillable = ['table_name', 'source_file', 'row_count', 'checksum', 'loaded_at'];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'loaded_at' => 'datetime',
        ];
    }
}
