<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'start_time',
        'end_time',
        'default_break_minutes',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_break_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function getIsOvernightAttribute(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    public function getDurationMinutesAttribute(): int
    {
        $start = Carbon::parse('2000-01-01 '.$this->start_time);
        $end = Carbon::parse('2000-01-01 '.$this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    public function getTimeRangeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A').' – '.Carbon::parse($this->end_time)->format('g:i A');
    }
}
