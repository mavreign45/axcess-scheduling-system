<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_assignment_id',
        'label',
        'start_at',
        'end_at',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function scheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class);
    }

    public function getDurationHoursAttribute(): float
    {
        return round(($this->duration_minutes ?: $this->start_at->diffInMinutes($this->end_at)) / 60, 2);
    }
}
