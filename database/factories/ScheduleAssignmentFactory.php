<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\ScheduleAssignment;
use App\Models\ShiftType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleAssignmentFactory extends Factory
{
    protected $model = ScheduleAssignment::class;

    public function definition(): array
    {
        $date = Carbon::now()->startOfWeek()->addDays(fake()->numberBetween(0, 6));
        $start = $date->copy()->setTime(9, 0);
        $end = $date->copy()->setTime(17, 0);

        return [
            'user_id' => User::factory(),
            'department_id' => Department::factory(),
            'shift_type_id' => ShiftType::factory(),
            'schedule_date' => $date->toDateString(),
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'published',
            'is_standby' => false,
            'standby_status' => 'not_applicable',
            'notes' => null,
        ];
    }

    public function overnight(): static
    {
        return $this->state(function () {
            $date = Carbon::now()->startOfWeek();

            return [
                'schedule_date' => $date->toDateString(),
                'start_at' => $date->copy()->setTime(18, 0),
                'end_at' => $date->copy()->addDay()->setTime(8, 0),
            ];
        });
    }

    public function standby(): static
    {
        return $this->state(fn () => [
            'is_standby' => true,
            'standby_status' => 'available',
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'status' => 'draft',
        ]);
    }
}
