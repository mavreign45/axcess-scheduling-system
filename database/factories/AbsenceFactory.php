<?php

namespace Database\Factories;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsenceFactory extends Factory
{
    protected $model = Absence::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'absence_date' => now()->subDays(fake()->numberBetween(1, 10))->toDateString(),
            'type' => fake()->randomElement(['sick', 'no_show', 'unauthorized', 'other']),
            'notes' => fake()->sentence(),
        ];
    }
}
