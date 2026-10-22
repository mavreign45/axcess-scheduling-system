<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Operations', 'Customer Support', 'Facilities', 'Warehouse', 'People & Culture',
        ]).' '.fake()->unique()->numerify('##');

        return [
            'name' => $name,
            'code' => strtoupper(Str::slug($name, '')),
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(['#0f766e', '#1d4ed8', '#b45309', '#7c3aed']),
            'min_staff_per_day' => fake()->numberBetween(1, 4),
            'is_active' => true,
        ];
    }
}
