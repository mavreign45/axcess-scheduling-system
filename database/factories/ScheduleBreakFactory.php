<?php

namespace Database\Factories;

use App\Models\ScheduleAssignment;
use App\Models\ScheduleBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleBreakFactory extends Factory
{
    protected $model = ScheduleBreak::class;

    public function definition(): array
    {
        $start = now()->startOfDay()->setTime(12, 0);

        return [
            'schedule_assignment_id' => ScheduleAssignment::factory(),
            'label' => 'Meal break',
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(30),
            'duration_minutes' => 30,
        ];
    }
}
