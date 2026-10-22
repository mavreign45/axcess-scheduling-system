<?php

namespace App\Models;

use App\Support\Axcess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'absence_date',
        'type',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'absence_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return Axcess::label('absence_type', $this->type);
    }
}
