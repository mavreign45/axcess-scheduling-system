<?php

namespace App\Models;

use App\Support\Axcess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'shift_type_id',
        'schedule_date',
        'start_at',
        'end_at',
        'status',
        'is_standby',
        'standby_status',
        'replacement_user_id',
        'replacement_reason',
        'replaced_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_standby' => 'boolean',
            'replaced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replacementUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class)->orderBy('start_at');
    }

    public function replacementHistory(): HasMany
    {
        return $this->hasMany(ReplacementHistory::class)->latest('replaced_at');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ScheduleConflict::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getScheduledMinutesAttribute(): int
    {
        $end = $this->end_at->copy();

        if ($end->lessThanOrEqualTo($this->start_at)) {
            $end->addDay();
        }

        return (int) $this->start_at->diffInMinutes($end);
    }

    public function getScheduledHoursAttribute(): float
    {
        return round($this->scheduled_minutes / 60, 2);
    }

    public function getBreakMinutesAttribute(): int
    {
        return (int) $this->breaks->sum(function (ScheduleBreak $break): int {
            return $break->duration_minutes ?: (int) $break->start_at->diffInMinutes($break->end_at);
        });
    }

    public function getBreakHoursAttribute(): float
    {
        return round($this->break_minutes / 60, 2);
    }

    public function getNetMinutesAttribute(): int
    {
        return max(0, $this->scheduled_minutes - $this->break_minutes);
    }

    public function getNetHoursAttribute(): float
    {
        return round($this->net_minutes / 60, 2);
    }

    public function getBreakCountAttribute(): int
    {
        return $this->breaks->count();
    }

    public function getFirstBreakAttribute(): ?ScheduleBreak
    {
        return $this->breaks->sortBy('start_at')->first();
    }

    public function getIsOvernightAttribute(): bool
    {
        return ! $this->start_at->isSameDay($this->end_at);
    }

    public function getStatusLabelAttribute(): string
    {
        return Axcess::label('assignment_status', $this->status);
    }

    public function getStandbyStatusLabelAttribute(): string
    {
        return Axcess::label('standby_status', $this->standby_status);
    }

    public function getEmployeeNameAttribute(): string
    {
        return $this->user?->full_name ?? 'Unassigned';
    }

    public function getEffectiveEmployeeAttribute(): ?User
    {
        return $this->replacementUser ?? $this->user;
    }

    public function getEmployeeIdAttribute(): ?string
    {
        return $this->user?->employee_id;
    }

    public function getReplacementLabelAttribute(): ?string
    {
        if (! $this->replacementUser) {
            return null;
        }

        return "{$this->replacementUser->employee_id} — {$this->replacementUser->full_name}";
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $query;
        }

        if ($viewer->isManager()) {
            return $query->where('department_id', $viewer->department_id);
        }

        return $query->where(function (Builder $builder) use ($viewer): void {
            $builder->where('user_id', $viewer->id)
                ->orWhere('replacement_user_id', $viewer->id);
        });
    }

    public function scopeInPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('schedule_date', [$start->toDateString(), $end->toDateString()]);
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function scopeStandby(Builder $query): Builder
    {
        return $query->where('is_standby', true);
    }
}
