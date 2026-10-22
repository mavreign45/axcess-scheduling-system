<?php

namespace Database\Factories;

use App\Models\ShiftType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShiftTypeFactory extends Factory
{
    protected $model = ShiftType::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Morning', 'Afternoon', 'Evening', 'Night', 'Office', 'Standby',
        ]).' '.fake()->unique()->numerify('##');

        $start = fake()->randomElement(['06:00:00', '08:00:00', '14:00:00', '18:00:00', '22:00:00']);

        return [
            'name' => $name,
            'code' => strtoupper(Str::slug($name, '_')),
            'color' => fake()->randomElement(['#0d9488', '#2563eb', '#d97706', '#7c3aed']),
            'start_time' => $start,
            'end_time' => $start === '22:00:00' ? '06:00:00' : '14:00:00',
            'default_break_minutes' => 30,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function overnight(): static
    {
        return $this->state(fn () => [
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'default_break_minutes' => 45,
        ]);
    }
}
