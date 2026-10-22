<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 20));
        $days = fake()->numberBetween(1, 5);

        return [
            'user_id' => User::factory(),
            'leave_type' => fake()->randomElement(['annual', 'sick', 'personal', 'unpaid']),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
            'duration_days' => $days,
            'status' => 'pending',
            'reason' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
