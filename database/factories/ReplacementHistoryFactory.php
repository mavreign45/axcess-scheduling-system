<?php

namespace Database\Factories;

use App\Models\ReplacementHistory;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReplacementHistoryFactory extends Factory
{
    protected $model = ReplacementHistory::class;

    public function definition(): array
    {
        return [
            'schedule_assignment_id' => ScheduleAssignment::factory(),
            'original_user_id' => User::factory(),
            'replacement_user_id' => User::factory(),
            'replaced_at' => now(),
            'reason' => fake()->sentence(),
        ];
    }
}
