<?php

namespace App\Models;

use App\Support\Axcess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'department_id',
        'job_position_id',
        'role',
        'employment_status',
        'employment_type',
        'hire_date',
        'max_weekly_hours',
        'standby_eligible',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hire_date' => 'date',
            'max_weekly_hours' => 'float',
            'standby_eligible' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        $initials = mb_substr($this->first_name ?? '', 0, 1).mb_substr($this->last_name ?? '', 0, 1);

        return mb_strtoupper($initials ?: 'AX');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function canManageOperations(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function roleLabel(): string
    {
        return Axcess::label('role', $this->role);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function replacedSchedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class, 'replacement_user_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function offDays(): HasMany
    {
        return $this->hasMany(OffDay::class);
    }

    public function approvedLeaves(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->where('status', 'approved');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeEmployees(Builder $query): Builder
    {
        return $query->whereIn('role', ['employee', 'manager']);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $query;
        }

        if ($viewer->isManager()) {
            return $query->where('department_id', $viewer->department_id);
        }

        return $query->whereKey($viewer->getKey());
    }
}
