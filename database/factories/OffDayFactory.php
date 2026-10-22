<?php

namespace Database\Factories;

use App\Models\OffDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OffDayFactory extends Factory
{
    protected $model = OffDay::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->addDays(fake()->numberBetween(0, 13))->toDateString(),
            'type' => 'regular',
            'note' => null,
        ];
    }
}
