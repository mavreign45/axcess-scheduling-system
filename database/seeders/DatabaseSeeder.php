<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\Department;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\OffDay;
use App\Models\ReplacementHistory;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleBreak;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\ConflictDetector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $departments = $this->seedDepartments();
        $positions = $this->seedPositions($departments);
        $shiftTypes = $this->seedShiftTypes();
        $users = $this->seedUsers($departments, $positions);

        $this->seedSchedule($departments, $shiftTypes, $users);
        $this->seedLeaveAndAbsence($users);
        $this->seedReplacement($users);

        app(ConflictDetector::class)->detect(
            now()->startOfWeek()->subWeeks(2)->startOfDay(),
            now()->startOfWeek()->addWeeks(2)->endOfWeek()->endOfDay(),
        );
    }

    private function seedDepartments(): array
    {
        $data = [
            'OPS' => ['name' => 'Operations', 'description' => 'Core service delivery and dispatch operations.', 'color' => '#0f766e', 'min_staff_per_day' => 3],
            'CS' => ['name' => 'Customer Support', 'description' => 'Customer care, intake and service resolution.', 'color' => '#1d4ed8', 'min_staff_per_day' => 2],
            'FAC' => ['name' => 'Facilities', 'description' => 'Facilities upkeep, maintenance and workplace safety.', 'color' => '#b45309', 'min_staff_per_day' => 1],
        ];

        $departments = [];

        foreach ($data as $code => $attributes) {
            $departments[$code] = Department::updateOrCreate(
                ['code' => $code],
                $attributes + ['is_active' => true],
            );
        }

        return $departments;
    }

    private function seedPositions(array $departments): array
    {
        $data = [
            'OPS' => ['Operations Supervisor', 'Dispatch Coordinator', 'Shift Lead', 'Operations Associate'],
            'CS' => ['Support Lead', 'Support Specialist', 'Customer Care Associate'],
            'FAC' => ['Facilities Supervisor', 'Maintenance Technician'],
        ];

        $positions = [];

        foreach ($data as $code => $titles) {
            foreach ($titles as $title) {
                $positions[$code][$title] = JobPosition::updateOrCreate(
                    ['department_id' => $departments[$code]->id, 'title' => $title],
                    ['is_active' => true],
                );
            }
        }

        return $positions;
    }

    private function seedShiftTypes(): array
    {
        $data = [
            'MORN' => ['name' => 'Morning', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'default_break_minutes' => 30, 'color' => '#0d9488'],
            'DAY' => ['name' => 'Day Shift', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'default_break_minutes' => 30, 'color' => '#2563eb'],
            'AFT' => ['name' => 'Afternoon', 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'default_break_minutes' => 30, 'color' => '#7c3aed'],
            'NIGHT' => ['name' => 'Night Shift', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'default_break_minutes' => 45, 'color' => '#334155'],
            'EXT' => ['name' => 'Extended Night', 'start_time' => '18:00:00', 'end_time' => '08:00:00', 'default_break_minutes' => 60, 'color' => '#0f172a'],
            'STANDBY' => ['name' => 'Standby Duty', 'start_time' => '18:00:00', 'end_time' => '06:00:00', 'default_break_minutes' => 0, 'color' => '#d97706'],
        ];

        $shiftTypes = [];

        foreach ($data as $code => $attributes) {
            $shiftTypes[$code] = ShiftType::updateOrCreate(
                ['code' => $code],
                $attributes + ['is_active' => true, 'description' => $attributes['name'].' shift'],
            );
        }

        return $shiftTypes;
    }

    private function seedUsers(array $departments, array $positions): array
    {
        $data = [
            ['EMP-1001', 'Avery', 'Quinn', 'admin@axcess.test', 'admin', 'OPS', 'Operations Supervisor', false, 'full_time'],
            ['EMP-1002', 'Jordan', 'Reyes', 'manager@axcess.test', 'manager', 'OPS', 'Operations Supervisor', true, 'full_time'],
            ['EMP-1003', 'Priya', 'Shah', 'manager.cs@axcess.test', 'manager', 'CS', 'Support Lead', true, 'full_time'],
            ['EMP-1004', 'Liam', 'Bennett', 'liam.bennett@axcess.test', 'employee', 'OPS', 'Dispatch Coordinator', true, 'full_time'],
            ['EMP-1005', 'Maya', 'Chen', 'maya.chen@axcess.test', 'employee', 'OPS', 'Shift Lead', true, 'full_time'],
            ['EMP-1006', 'Noah', 'Patel', 'noah.patel@axcess.test', 'employee', 'OPS', 'Operations Associate', false, 'part_time'],
            ['EMP-1007', 'Sofia', 'Ramirez', 'sofia.ramirez@axcess.test', 'employee', 'OPS', 'Operations Associate', true, 'full_time'],
            ['EMP-1008', 'Ethan', 'Brooks', 'ethan.brooks@axcess.test', 'employee', 'OPS', 'Operations Associate', false, 'full_time'],
            ['EMP-1009', 'Ava', 'Thompson', 'ava.thompson@axcess.test', 'employee', 'CS', 'Support Specialist', true, 'full_time'],
            ['EMP-1010', 'Lucas', 'Nguyen', 'lucas.nguyen@axcess.test', 'employee', 'CS', 'Customer Care Associate', false, 'part_time'],
            ['EMP-1011', 'Emma', 'Wilson', 'emma.wilson@axcess.test', 'employee', 'CS', 'Support Specialist', true, 'full_time'],
            ['EMP-1012', 'Daniel', 'Kim', 'daniel.kim@axcess.test', 'employee', 'CS', 'Customer Care Associate', false, 'contract'],
            ['EMP-1013', 'Olivia', 'Grant', 'olivia.grant@axcess.test', 'employee', 'FAC', 'Facilities Supervisor', true, 'full_time'],
            ['EMP-1014', 'Marcus', 'Reed', 'marcus.reed@axcess.test', 'employee', 'FAC', 'Maintenance Technician', false, 'full_time'],
        ];

        $users = [];

        foreach ($data as [$employeeId, $firstName, $lastName, $email, $role, $department, $position, $standby, $employmentType]) {
            $users[$employeeId] = User::updateOrCreate(
                ['employee_id' => $employeeId],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password' => 'password',
                    'phone' => '+1 555 '.fake()->numerify('### ####'),
                    'department_id' => $departments[$department]->id,
                    'job_position_id' => $positions[$department][$position]->id,
                    'role' => $role,
                    'employment_status' => 'active',
                    'employment_type' => $employmentType,
                    'hire_date' => now()->subYears(2)->subDays(30)->toDateString(),
                    'max_weekly_hours' => $employmentType === 'part_time' ? 24 : 40,
                    'standby_eligible' => $standby,
                    'is_active' => true,
                ],
            );
        }

        return $users;
    }

    private function seedSchedule(array $departments, array $shiftTypes, array $users): void
    {
        $employees = collect($users)
            ->filter(fn (User $user) => $user->role === 'employee')
            ->values();

        $start = now()->startOfWeek()->subWeeks(2)->startOfDay();
        $end = now()->startOfWeek()->addWeeks(2)->endOfWeek()->startOfDay();
        $admin = $users['EMP-1001'];
        $today = now()->startOfDay();

        $typeCodes = [
            'OPS' => ['MORN', 'DAY', 'AFT', 'NIGHT'],
            'CS' => ['MORN', 'DAY', 'AFT'],
            'FAC' => ['DAY'],
        ];

        foreach ($employees as $index => $employee) {
            $department = Department::find($employee->department_id);
            $offOne = ($index % 7) + 1;
            $offTwo = (($index + 3) % 7) + 1;
            $codes = $typeCodes[$department->code] ?? ['DAY'];

            $date = $start->copy();

            while ($date->lte($end)) {
                $dayOfWeek = $date->dayOfWeekIso;

                if ($dayOfWeek === $offOne || $dayOfWeek === $offTwo) {
                    OffDay::firstOrCreate(
                        ['user_id' => $employee->id, 'date' => $date->toDateString()],
                        ['type' => 'regular'],
                    );
                    $date->addDay();

                    continue;
                }

                $shiftType = $shiftTypes[$codes[($index + $dayOfWeek) % count($codes)]];
                $shiftStart = $date->copy()->setTimeFromTimeString($shiftType->start_time);
                $shiftEnd = $date->copy()->setTimeFromTimeString($shiftType->end_time);

                if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $assignment = ScheduleAssignment::create([
                    'user_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'shift_type_id' => $shiftType->id,
                    'schedule_date' => $date->toDateString(),
                    'start_at' => $shiftStart,
                    'end_at' => $shiftEnd,
                    'status' => $date->lt($today) ? 'completed' : ($date->isToday() ? 'confirmed' : 'published'),
                    'is_standby' => false,
                    'standby_status' => 'not_applicable',
                    'notes' => $dayOfWeek === 6 ? 'Weekend coverage.' : null,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);

                $this->addBreaks($assignment);

                if ($department->code === 'OPS' && $date->isSameDay(now()->startOfWeek()) && $employee->employee_id === 'EMP-1007') {
                    $assignment->delete();
                    $extended = $shiftTypes['EXT'];
                    $startAt = $date->copy()->setTime(18, 0);
                    $endAt = $date->copy()->addDay()->setTime(8, 0);

                    $assignment = ScheduleAssignment::create([
                        'user_id' => $employee->id,
                        'department_id' => $employee->department_id,
                        'shift_type_id' => $extended->id,
                        'schedule_date' => $date->toDateString(),
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'status' => 'published',
                        'is_standby' => false,
                        'standby_status' => 'not_applicable',
                        'notes' => 'Extended overnight coverage — handover at 08:00.',
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                    ]);

                    ScheduleBreak::create(['schedule_assignment_id' => $assignment->id, 'label' => 'Meal break', 'start_at' => $startAt->copy()->addHours(4), 'end_at' => $startAt->copy()->addHours(4)->addMinutes(30), 'duration_minutes' => 30]);
                    ScheduleBreak::create(['schedule_assignment_id' => $assignment->id, 'label' => 'Rest break', 'start_at' => $startAt->copy()->addHours(8), 'end_at' => $startAt->copy()->addHours(8)->addMinutes(30), 'duration_minutes' => 30]);
                }

                $date->addDay();
            }
        }

        // Standby duties on Friday for each department.
        foreach (['OPS', 'CS', 'FAC'] as $departmentCode) {
            $date = now()->startOfWeek()->addDays(4);
            $startAt = $date->copy()->setTime(18, 0);
            $endAt = $date->copy()->addDay()->setTime(6, 0);
            $candidate = $employees
                ->where('department_id', $departments[$departmentCode]->id)
                ->where('standby_eligible', true)
                ->first(fn (User $employee) => ! ScheduleAssignment::where('user_id', $employee->id)
                    ->whereDate('schedule_date', $date->toDateString())
                    ->exists());

            if ($candidate) {
                ScheduleAssignment::create([
                    'user_id' => $candidate->id,
                    'department_id' => $candidate->department_id,
                    'shift_type_id' => $shiftTypes['STANDBY']->id,
                    'schedule_date' => $date->toDateString(),
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => 'published',
                    'is_standby' => true,
                    'standby_status' => $departmentCode === 'OPS' ? 'called_in' : 'available',
                    'notes' => 'Standby duty — remain contactable.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);
            }
        }

        // Open shifts that still need cover.
        foreach ([0, 1] as $weekOffset) {
            $date = now()->startOfWeek()->addWeeks($weekOffset)->addDays(5);
            $startAt = $date->copy()->setTime(9, 0);
            ScheduleAssignment::create([
                'user_id' => null,
                'department_id' => $departments['OPS']->id,
                'shift_type_id' => $shiftTypes['DAY']->id,
                'schedule_date' => $date->toDateString(),
                'start_at' => $startAt,
                'end_at' => $startAt->copy()->addHours(8),
                'status' => 'draft',
                'is_standby' => false,
                'standby_status' => 'not_applicable',
                'notes' => 'Open shift — awaiting assignment.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        // Deliberate double booking for a manager to review.
        $doubleDate = now()->startOfWeek()->addDays(2);
        $doubleUser = $employees->firstWhere('employee_id', 'EMP-1007');
        $existing = ScheduleAssignment::where('user_id', $doubleUser->id)
            ->whereDate('schedule_date', $doubleDate->toDateString())
            ->where('is_standby', false)
            ->first();

        if ($existing) {
            ScheduleAssignment::create([
                'user_id' => $doubleUser->id,
                'department_id' => $doubleUser->department_id,
                'shift_type_id' => $shiftTypes['AFT']->id,
                'schedule_date' => $doubleDate->toDateString(),
                'start_at' => $doubleDate->copy()->setTime(15, 0),
                'end_at' => $doubleDate->copy()->setTime(23, 0),
                'status' => 'published',
                'is_standby' => false,
                'standby_status' => 'not_applicable',
                'notes' => 'Coverage overlap to be reviewed.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }
    }

    private function addBreaks(ScheduleAssignment $assignment): void
    {
        $start = $assignment->start_at->copy();
        $hours = $assignment->scheduled_hours;

        if ($hours > 10) {
            foreach ([[4, 0.5], [8, 0.5]] as [$offset, $duration]) {
                $breakStart = $start->copy()->addHours($offset);
                ScheduleBreak::create([
                    'schedule_assignment_id' => $assignment->id,
                    'label' => $offset === 4 ? 'Meal break' : 'Rest break',
                    'start_at' => $breakStart,
                    'end_at' => $breakStart->copy()->addMinutes((int) ($duration * 60)),
                    'duration_minutes' => (int) ($duration * 60),
                ]);
            }

            return;
        }

        $breakStart = $start->copy()->addHours(4);
        ScheduleBreak::create([
            'schedule_assignment_id' => $assignment->id,
            'label' => 'Meal break',
            'start_at' => $breakStart,
            'end_at' => $breakStart->copy()->addMinutes(30),
            'duration_minutes' => 30,
        ]);
    }

    private function seedLeaveAndAbsence(array $users): void
    {
        $approver = $users['EMP-1002'];
        $csApprover = $users['EMP-1003'];

        LeaveRequest::updateOrCreate(
            ['user_id' => $users['EMP-1005']->id, 'start_date' => now()->startOfWeek()->addDay()->toDateString()],
            [
                'leave_type' => 'annual',
                'end_date' => now()->startOfWeek()->addDays(2)->toDateString(),
                'duration_days' => 2,
                'status' => 'approved',
                'reason' => 'Family trip booked earlier this year.',
                'supporting_information' => 'Travel confirmation on file.',
                'approver_id' => $approver->id,
                'approved_at' => now()->subDays(3),
            ],
        );

        LeaveRequest::updateOrCreate(
            ['user_id' => $users['EMP-1010']->id, 'start_date' => now()->startOfWeek()->addWeek()->addDay()->toDateString()],
            [
                'leave_type' => 'personal',
                'end_date' => now()->startOfWeek()->addWeek()->addDay()->toDateString(),
                'duration_days' => 1,
                'status' => 'pending',
                'reason' => 'Personal appointment.',
                'approver_id' => null,
            ],
        );

        LeaveRequest::updateOrCreate(
            ['user_id' => $users['EMP-1011']->id, 'start_date' => now()->subWeek()->startOfWeek()->addDay()->toDateString()],
            [
                'leave_type' => 'sick',
                'end_date' => now()->subWeek()->startOfWeek()->addDays(2)->toDateString(),
                'duration_days' => 2,
                'status' => 'approved',
                'reason' => 'Medical certificate submitted.',
                'supporting_information' => 'Medical certificate reference MC-2214.',
                'approver_id' => $csApprover->id,
                'approved_at' => now()->subWeek()->startOfWeek(),
            ],
        );

        LeaveRequest::updateOrCreate(
            ['user_id' => $users['EMP-1008']->id, 'start_date' => now()->addWeek()->startOfWeek()->toDateString()],
            [
                'leave_type' => 'unpaid',
                'end_date' => now()->addWeek()->startOfWeek()->addDay()->toDateString(),
                'duration_days' => 2,
                'status' => 'rejected',
                'reason' => 'Personal matters.',
                'approver_id' => $approver->id,
                'rejection_reason' => 'Coverage cannot be maintained during this period.',
            ],
        );

        Absence::updateOrCreate(
            ['user_id' => $users['EMP-1013']->id, 'absence_date' => now()->subDay()->toDateString()],
            [
                'type' => 'sick',
                'notes' => 'Notified the duty manager at 07:15.',
                'recorded_by' => $approver->id,
            ],
        );

        Absence::updateOrCreate(
            ['user_id' => $users['EMP-1012']->id, 'absence_date' => now()->subDays(3)->toDateString()],
            [
                'type' => 'no_show',
                'notes' => 'No call received; follow-up completed.',
                'recorded_by' => $csApprover->id,
            ],
        );
    }

    private function seedReplacement(array $users): void
    {
        $original = $users['EMP-1004'];
        $assignment = ScheduleAssignment::query()
            ->where('user_id', $original->id)
            ->whereDate('schedule_date', '>=', now()->startOfWeek()->toDateString())
            ->whereDate('schedule_date', '<=', now()->endOfWeek()->toDateString())
            ->notCancelled()
            ->orderBy('schedule_date')
            ->first();

        if (! $assignment) {
            return;
        }

        $date = $assignment->schedule_date->copy();
        $replacement = User::query()
            ->where('department_id', $assignment->department_id)
            ->where('is_active', true)
            ->whereIn('role', ['employee', 'manager'])
            ->whereKeyNot($original->id)
            ->whereDoesntHave('schedules', fn ($query) => $query->whereDate('schedule_date', $date->toDateString())->notCancelled())
            ->first();

        if (! $replacement) {
            return;
        }

        DB::transaction(function () use ($assignment, $original, $replacement, $users): void {
            ReplacementHistory::create([
                'schedule_assignment_id' => $assignment->id,
                'original_user_id' => $original->id,
                'replacement_user_id' => $replacement->id,
                'replaced_by' => $users['EMP-1002']->id,
                'replaced_at' => now()->subDay(),
                'reason' => 'Original employee unavailable; replacement confirmed.',
            ]);

            $assignment->update([
                'replacement_user_id' => $replacement->id,
                'replacement_reason' => 'Original employee unavailable; replacement confirmed.',
                'replaced_at' => now()->subDay(),
            ]);
        });
    }
}
