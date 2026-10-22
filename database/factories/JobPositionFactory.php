<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\JobPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPositionFactory extends Factory
{
    protected $model = JobPosition::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'title' => fake()->randomElement([
                'Team Lead', 'Coordinator', 'Specialist', 'Associate', 'Supervisor',
            ]),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
