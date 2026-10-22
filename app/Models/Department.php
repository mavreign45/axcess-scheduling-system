<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'color',
        'min_staff_per_day',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_staff_per_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ScheduleConflict::class);
    }
}
