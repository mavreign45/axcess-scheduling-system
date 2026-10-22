<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OffDay;
use App\Models\ReplacementHistory;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleConflict;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function staffSummary(Carbon $start, Carbon $end, ?int $departmentId = null, ?int $userId = null): Collection
    {
        $assignments = ScheduleAssignment::query()
            ->with(['user.department', 'user.jobPosition', 'replacementUser', 'shiftType', 'breaks', 'department'])
            ->inPeriod($start, $end)
            ->notCancelled()
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($userId, fn ($query) => $query->where(function ($scope) use ($userId): void {
                $scope->where('user_id', $userId)->orWhere('replacement_user_id', $userId);
            }))
            ->get();

        $assignmentsByEmployee = $assignments
            ->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee !== null)
            ->groupBy(fn (ScheduleAssignment $assignment) => $assignment->effective_employee->id);

        $userIdsWithAssignments = $assignmentsByEmployee->keys()->all();

        $users = User::query()
            ->with(['department', 'jobPosition'])
            ->whereIn('role', ['employee', 'manager'])
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->where(function ($query) use ($userIdsWithAssignments): void {
                $query->where('is_active', true);

                if ($userIdsWithAssignments !== []) {
                    $query->orWhereIn('id', $userIdsWithAssignments);
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $leaves = LeaveRequest::query()
            ->with('user')
            ->where('status', 'approved')
            ->overlappingPeriod($start, $end)
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
            ->get()
            ->groupBy('user_id');

        $offDays = OffDay::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
            ->get()
            ->countBy('user_id');

        $absences = Absence::query()
            ->whereDate('absence_date', '>=', $start->toDateString())
            ->whereDate('absence_date', '<=', $end->toDateString())
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
            ->get()
            ->countBy('user_id');

        return $users->map(function (User $user) use ($assignmentsByEmployee, $leaves, $offDays, $absences, $start, $end): array {
            $userAssignments = $assignmentsByEmployee->get($user->id, collect());
            $shifts = $userAssignments->where('is_standby', false);
            $standby = $userAssignments->where('is_standby', true);

            $leaveDays = $leaves->get($user->id, collect())->sum(
                fn (LeaveRequest $leave) => $this->overlapDays($leave->start_date, $leave->end_date, $start, $end)
            );

            return [
                'user' => $user,
                'shift_count' => $shifts->count(),
                'scheduled_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->scheduled_hours), 2),
                'break_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->break_hours), 2),
                'net_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2),
                'standby_count' => $standby->count(),
                'standby_hours' => round($standby->sum(fn (ScheduleAssignment $assignment) => $assignment->scheduled_hours), 2),
                'off_days' => (int) ($offDays[$user->id] ?? 0),
                'leave_days' => round((float) $leaveDays, 1),
                'absence_count' => (int) ($absences[$user->id] ?? 0),
                'replacement_count' => $userAssignments->where('replacement_user_id', $user->id)->count(),
            ];
        });
    }

    public function coverageRows(Carbon $start, Carbon $end, ?int $departmentId = null): Collection
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->whereKey($departmentId))
            ->orderBy('name')
            ->get();

        $assignments = ScheduleAssignment::query()
            ->with(['user', 'replacementUser'])
            ->inPeriod($start, $end)
            ->notCancelled()
            ->where('is_standby', false)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get();

        $grouped = $assignments->groupBy(
            fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString().'|'.$assignment->department_id
        );

        $rows = collect();
        $date = $start->copy()->startOfDay();

        while ($date->lte($end)) {
            foreach ($departments as $department) {
                $cell = $grouped->get($date->toDateString().'|'.$department->id, collect());
                $scheduledStaff = $cell
                    ->map(fn (ScheduleAssignment $assignment) => $assignment->effective_employee?->id)
                    ->filter()
                    ->unique()
                    ->count();
                $unassigned = $cell
                    ->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee === null)
                    ->count();
                $required = (int) $department->min_staff_per_day;
                $gap = max(0, $required - $scheduledStaff);

                $rows->push([
                    'date' => $date->copy(),
                    'day' => $date->format('l'),
                    'department' => $department->name,
                    'department_id' => $department->id,
                    'required' => $required,
                    'scheduled' => $scheduledStaff,
                    'unassigned' => $unassigned,
                    'gap' => $gap,
                    'coverage_percent' => $required > 0 ? min(1, $scheduledStaff / $required) : 1,
                    'status' => $gap > 0 ? 'Coverage gap' : 'Covered',
                ]);
            }

            $date->addDay();
        }

        return $rows;
    }

    public function breakCoverageRows(Carbon $start, Carbon $end, ?int $departmentId = null): Collection
    {
        return ScheduleAssignment::query()
            ->with(['user', 'replacementUser', 'department', 'shiftType', 'breaks'])
            ->inPeriod($start, $end)
            ->notCancelled()
            ->where('is_standby', false)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('start_at')
            ->get()
            ->map(function (ScheduleAssignment $assignment): array {
                $hours = $assignment->scheduled_hours;
                $breakHours = $assignment->break_hours;
                $minimumBreak = config('axcess.scheduling.minimum_break_after_hours', 6);

                $status = match (true) {
                    $hours >= $minimumBreak && $assignment->break_count === 0 => 'Missing',
                    $hours >= 8 && $breakHours < 0.5 => 'Insufficient',
                    $hours >= $minimumBreak => 'Covered',
                    default => 'Not required',
                };

                $employee = $assignment->effective_employee;

                return [
                    'assignment' => $assignment,
                    'date' => $assignment->schedule_date->copy(),
                    'employee_id' => $employee?->employee_id,
                    'employee_name' => $employee?->full_name ?? 'Unassigned',
                    'department' => $assignment->department->name,
                    'shift_type' => $assignment->shiftType->name,
                    'shift_hours' => $hours,
                    'break_count' => $assignment->break_count,
                    'break_hours' => $breakHours,
                    'break_percent' => $hours > 0 ? min(1, $breakHours / $hours) : 0,
                    'status' => $status,
                    'detail' => $status === 'Missing'
                        ? 'No break recorded for a shift of '.number_format($hours, 2).' hours.'
                        : ($status === 'Insufficient'
                            ? 'Break entitlement is below 30 minutes for a shift of '.number_format($hours, 2).' hours.'
                            : ''),
                ];
            });
    }

    public function unassignedRows(Carbon $start, Carbon $end, ?int $departmentId = null): Collection
    {
        return ScheduleAssignment::query()
            ->with(['department', 'shiftType'])
            ->inPeriod($start, $end)
            ->notCancelled()
            ->whereNull('user_id')
            ->whereNull('replacement_user_id')
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('start_at')
            ->get();
    }

    public function replacementRows(Carbon $start, Carbon $end, ?int $departmentId = null): Collection
    {
        return ReplacementHistory::query()
            ->with(['scheduleAssignment.department', 'scheduleAssignment.shiftType', 'originalUser', 'replacementUser', 'replacedBy'])
            ->whereHas('scheduleAssignment', function ($query) use ($start, $end, $departmentId): void {
                $query->inPeriod($start, $end)
                    ->when($departmentId, fn ($scope) => $scope->where('department_id', $departmentId));
            })
            ->latest('replaced_at')
            ->get();
    }

    public function conflicts(Carbon $start, Carbon $end, ?int $departmentId = null, bool $openOnly = true): Collection
    {
        return ScheduleConflict::query()
            ->with(['scheduleAssignment.shiftType', 'user', 'department', 'resolver'])
            ->whereDate('conflict_date', '>=', $start->toDateString())
            ->whereDate('conflict_date', '<=', $end->toDateString())
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($openOnly, fn ($query) => $query->where('status', 'open'))
            ->orderByDesc('conflict_date')
            ->orderByRaw("CASE severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->get();
    }

    public function metrics(Carbon $start, Carbon $end, ?int $departmentId = null): array
    {
        $assignments = ScheduleAssignment::query()
            ->with(['breaks'])
            ->inPeriod($start, $end)
            ->notCancelled()
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get();

        $shifts = $assignments->where('is_standby', false);
        $standby = $assignments->where('is_standby', true);
        $coverageRows = $this->coverageRows($start, $end, $departmentId);
        $breakRows = $this->breakCoverageRows($start, $end, $departmentId);

        $approvedLeaves = LeaveRequest::query()
            ->where('status', 'approved')
            ->overlappingPeriod($start, $end)
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
            ->get();

        $leaveDays = $approvedLeaves->sum(fn (LeaveRequest $leave) => $this->overlapDays($leave->start_date, $leave->end_date, $start, $end));

        $pendingLeaves = LeaveRequest::query()
            ->where('status', 'pending')
            ->overlappingPeriod($start, $end)
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
            ->count();

        return [
            'total_staff' => User::query()
                ->whereIn('role', ['employee', 'manager'])
                ->where('is_active', true)
                ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
                ->count(),
            'staff_scheduled' => $shifts
                ->map(fn (ScheduleAssignment $assignment) => $assignment->effective_employee?->id)
                ->filter()
                ->unique()
                ->count(),
            'assignment_count' => $assignments->count(),
            'shift_count' => $shifts->count(),
            'scheduled_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->scheduled_hours), 2),
            'break_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->break_hours), 2),
            'net_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2),
            'standby_count' => $standby->count(),
            'standby_hours' => round($standby->sum(fn (ScheduleAssignment $assignment) => $assignment->scheduled_hours), 2),
            'unassigned_shifts' => $assignments
                ->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee === null)
                ->count(),
            'coverage_gaps' => $coverageRows->where('gap', '>', 0)->count(),
            'approved_leave_days' => round((float) $leaveDays, 1),
            'pending_leave_requests' => $pendingLeaves,
            'absences' => Absence::query()
                ->whereDate('absence_date', '>=', $start->toDateString())
                ->whereDate('absence_date', '<=', $end->toDateString())
                ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
                ->count(),
            'open_conflicts' => $this->conflicts($start, $end, $departmentId)->count(),
            'missing_breaks' => $breakRows->whereIn('status', ['Missing', 'Insufficient'])->count(),
            'replacements' => $this->replacementRows($start, $end, $departmentId)->count(),
        ];
    }

    public function overlapDays(Carbon $from, Carbon $to, Carbon $periodStart, Carbon $periodEnd): int
    {
        $start = $from->greaterThan($periodStart) ? $from->copy() : $periodStart->copy();
        $end = $to->lessThan($periodEnd) ? $to->copy() : $periodEnd->copy();

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
    }
}
