<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'employee_id' => 'EMP-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'phone' => fake()->numerify('+1 555 ### ####'),
            'department_id' => Department::factory(),
            'job_position_id' => null,
            'role' => 'employee',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'hire_date' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'max_weekly_hours' => 40,
            'standby_eligible' => fake()->boolean(60),
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'email' => 'admin@axcess.test']);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => 'manager']);
    }
}
