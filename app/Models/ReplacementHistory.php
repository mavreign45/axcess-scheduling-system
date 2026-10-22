<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementHistory extends Model
{
    use HasFactory;

    protected $table = 'replacement_history';

    protected $fillable = [
        'schedule_assignment_id',
        'original_user_id',
        'replacement_user_id',
        'replaced_by',
        'replaced_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'replaced_at' => 'datetime',
        ];
    }

    public function scheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class);
    }

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function replacementUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_user_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }
}
