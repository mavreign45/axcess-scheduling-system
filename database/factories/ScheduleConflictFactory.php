<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\ScheduleConflict;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ScheduleConflictFactory extends Factory
{
    protected $model = ScheduleConflict::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['leave_conflict', 'double_booking', 'coverage_gap', 'missing_break']);

        return [
            'department_id' => Department::factory(),
            'conflict_date' => now()->toDateString(),
            'type' => $type,
            'severity' => in_array($type, ['coverage_gap', 'double_booking'], true) ? 'error' : 'warning',
            'title' => Str::headline($type),
            'description' => fake()->sentence(),
            'status' => 'open',
            'source' => 'manual',
            'fingerprint' => sha1($type.fake()->uuid().microtime()),
        ];
    }
}
