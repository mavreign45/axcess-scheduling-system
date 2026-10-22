<?php

namespace App\Models;

use App\Support\Axcess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleConflict extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_assignment_id',
        'department_id',
        'user_id',
        'conflict_date',
        'type',
        'severity',
        'title',
        'description',
        'status',
        'source',
        'fingerprint',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'conflict_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function scheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return Axcess::label('conflict_type', $this->type);
    }

    public function getSeverityLabelAttribute(): string
    {
        return Axcess::label('conflict_severity', $this->severity);
    }

    public function getStatusLabelAttribute(): string
    {
        return Axcess::label('conflict_status', $this->status);
    }
}
