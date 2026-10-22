<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_overnight_shift_with_every_break(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');

        $department = Department::factory()->create(['name' => 'Operations', 'code' => 'OPS']);
        $manager = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'manager',
            'employee_id' => 'EMP-1000',
            'email' => 'manager@axcess.test',
        ]);
        $employee = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'employee',
            'employee_id' => 'EMP-1001',
            'email' => 'employee@axcess.test',
        ]);
        $shift = ShiftType::factory()->create([
            'name' => 'Extended Night',
            'code' => 'EXTENDED_NIGHT',
            'start_time' => '22:00:00',
            'end_time' => '08:00:00',
        ]);

        $assignment = app(ScheduleService::class)->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'shift_type_id' => $shift->id,
            'schedule_date' => '2026-10-19',
            'start_at' => '2026-10-19 18:00:00',
            'end_at' => '2026-10-20 08:00:00',
            'status' => 'published',
            'breaks' => [
                ['label' => 'Meal break', 'start_at' => '2026-10-19 22:00:00', 'end_at' => '2026-10-19 22:30:00'],
                ['label' => 'Rest break', 'start_at' => '2026-10-20 02:00:00', 'end_at' => '2026-10-20 02:30:00'],
            ],
        ], $manager);

        $this->assertTrue($assignment->is_overnight);
        $this->assertSame(14.0, $assignment->scheduled_hours);
        $this->assertSame(1.0, $assignment->break_hours);
        $this->assertSame(13.0, $assignment->net_hours);
        $this->assertCount(2, $assignment->breaks);
        $this->assertSame(['Meal break', 'Rest break'], $assignment->breaks->pluck('label')->all());
        $this->assertSame('2026-10-20 02:30:00', $assignment->breaks->last()->end_at->format('Y-m-d H:i:s'));
    }

    public function test_it_replaces_all_breaks_when_a_shift_is_updated(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');

        $department = Department::factory()->create(['name' => 'Operations', 'code' => 'OPS']);
        $manager = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'manager',
            'employee_id' => 'EMP-2000',
            'email' => 'manager2@axcess.test',
        ]);
        $employee = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'employee',
            'employee_id' => 'EMP-2001',
            'email' => 'employee2@axcess.test',
        ]);
        $shift = ShiftType::factory()->create([
            'name' => 'Day Shift',
            'code' => 'DAY',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        $assignment = app(ScheduleService::class)->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'shift_type_id' => $shift->id,
            'schedule_date' => '2026-10-19',
            'start_at' => '2026-10-19 08:00:00',
            'end_at' => '2026-10-19 16:00:00',
            'breaks' => [
                ['label' => 'Meal break', 'start_at' => '2026-10-19 12:00:00', 'end_at' => '2026-10-19 12:30:00'],
            ],
        ], $manager);

        $updated = app(ScheduleService::class)->update($assignment, [
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'shift_type_id' => $shift->id,
            'schedule_date' => '2026-10-19',
            'start_at' => '2026-10-19 08:00:00',
            'end_at' => '2026-10-19 16:00:00',
            'breaks' => [
                ['label' => 'Meal break', 'start_at' => '2026-10-19 11:30:00', 'end_at' => '2026-10-19 12:00:00'],
                ['label' => 'Rest break', 'start_at' => '2026-10-19 14:00:00', 'end_at' => '2026-10-19 14:15:00'],
            ],
        ], $manager);

        $this->assertCount(2, $updated->breaks);
        $this->assertSame(['Meal break', 'Rest break'], $updated->breaks->pluck('label')->all());
        $this->assertSame(0.75, $updated->break_hours);
    }
}
