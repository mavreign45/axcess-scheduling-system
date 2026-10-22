<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'format',
        'file_name',
        'filters',
        'row_count',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'row_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
