<?php

namespace App\Models;

use App\Support\Axcess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_type',
        'start_date',
        'end_date',
        'duration_days',
        'status',
        'reason',
        'supporting_information',
        'approver_id',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getLeaveTypeLabelAttribute(): string
    {
        return Axcess::label('leave_type', $this->leave_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return Axcess::label('leave_status', $this->status);
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getDateRangeLabelAttribute(): string
    {
        if ($this->start_date->isSameDay($this->end_date)) {
            return $this->start_date->format('M j, Y');
        }

        return $this->start_date->format('M j, Y').' – '.$this->end_date->format('M j, Y');
    }

    public function getDurationLabelAttribute(): string
    {
        return rtrim(rtrim(number_format($this->duration_days, 1), '0'), '.').' day(s)';
    }

    public static function calculateDuration(Carbon $start, Carbon $end): float
    {
        return round($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1, 1);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $query;
        }

        if ($viewer->isManager()) {
            return $query->whereHas('user', fn (Builder $user) => $user->where('department_id', $viewer->department_id));
        }

        return $query->where('user_id', $viewer->id);
    }

    public function scopeOverlappingPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());
    }

    public function overlapsDate(Carbon $date): bool
    {
        return $this->start_date->lte($date->copy()->endOfDay())
            && $this->end_date->gte($date->copy()->startOfDay());
    }
}
