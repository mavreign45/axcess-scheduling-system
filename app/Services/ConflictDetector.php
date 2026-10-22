<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleConflict;
use App\Support\Axcess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConflictDetector
{
    public function __construct(private readonly ReportService $reports) {}

    public function detect(Carbon $start, Carbon $end, ?int $departmentId = null): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        ScheduleConflict::query()
            ->where('source', 'auto')
            ->where('status', 'open')
            ->whereDate('conflict_date', '>=', $start->toDateString())
            ->whereDate('conflict_date', '<=', $end->toDateString())
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->delete();

        $assignments = ScheduleAssignment::query()
            ->with(['user', 'replacementUser', 'department', 'shiftType', 'breaks'])
            ->whereDate('schedule_date', '>=', $start->toDateString())
            ->whereDate('schedule_date', '<=', $end->toDateString())
            ->where('status', '!=', 'cancelled')
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('start_at')
            ->get();

        $leaves = LeaveRequest::query()
            ->where('status', 'approved')
            ->overlappingPeriod($start, $end)
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $departmentId)))
            ->get()
            ->groupBy('user_id');

        $created = 0;

        foreach ($assignments as $assignment) {
            $employee = $assignment->effective_employee;

            if ($employee === null) {
                $created += $this->persist($assignment, 'unassigned_shift', 'error', $assignment->schedule_date, null, 'Unassigned shift', "{$assignment->shiftType->name} on {$assignment->schedule_date->format('M j, Y')} has no assigned employee.");
            }

            if ($assignment->user && ! $assignment->replacement_user_id) {
                $userLeaves = $leaves->get($assignment->user_id, collect());
                $leave = $userLeaves->first(fn (LeaveRequest $leave) => $leave->overlapsDate($assignment->schedule_date));

                if ($leave) {
                    $created += $this->persist($assignment, 'leave_conflict', 'error', $assignment->schedule_date, $assignment->user_id, 'Employee on approved leave', "{$assignment->user->full_name} is scheduled for {$assignment->shiftType->name} during approved {$leave->leave_type_label} ({$leave->date_range_label}).");
                }
            }

            $hours = $assignment->scheduled_hours;

            // Break entitlement applies to worked shifts. Standby duties may
            // legitimately span long periods without formal break periods.
            if (! $assignment->is_standby) {
                if ($hours >= (float) config('axcess.scheduling.minimum_break_after_hours', 6) && $assignment->break_count === 0) {
                    $created += $this->persist($assignment, 'missing_break', 'warning', $assignment->schedule_date, $assignment->effective_employee?->id, 'Missing break coverage', "A {$hours}-hour shift has no break recorded.");
                } elseif ($hours >= 8 && $assignment->break_hours < 0.5) {
                    $created += $this->persist($assignment, 'missing_break', 'warning', $assignment->schedule_date, $assignment->effective_employee?->id, 'Insufficient break coverage', "A {$hours}-hour shift has less than 30 minutes of break time.");
                }

                if ($hours > (float) config('axcess.scheduling.long_shift_hours', 12)) {
                    $created += $this->persist($assignment, 'long_shift', 'warning', $assignment->schedule_date, $assignment->effective_employee?->id, 'Long shift', "Shift duration is {$hours} hours.");
                }
            }

            if ($assignment->is_standby && $assignment->user && ! $assignment->user->standby_eligible) {
                $created += $this->persist($assignment, 'standby_ineligible', 'warning', $assignment->schedule_date, $assignment->user_id, 'Standby eligibility', "{$assignment->user->full_name} is not marked as standby eligible.");
            }
        }

        $created += $this->detectDoubleBookings($assignments);

        $created += $this->detectWeeklyOvertime($assignments);

        $coverageRows = $this->reports->coverageRows($start, $end, $departmentId)
            ->where('gap', '>', 0);

        foreach ($coverageRows as $row) {
            $departmentLabel = $row['department'];
            $fingerprint = $this->fingerprint('coverage_gap', null, $row['date']->toDateString(), $departmentLabel);

            $created += $this->storeIfNew([
                'schedule_assignment_id' => null,
                'department_id' => $row['department_id'],
                'user_id' => null,
                'conflict_date' => $row['date']->toDateString(),
                'type' => 'coverage_gap',
                'severity' => 'error',
                'title' => 'Staffing coverage gap',
                'description' => "{$departmentLabel} has {$row['scheduled']} staff scheduled against a minimum of {$row['required']} on {$row['date']->format('M j, Y')}.",
                'status' => 'open',
                'source' => 'auto',
                'fingerprint' => $fingerprint,
            ]);
        }

        return $created;
    }

    private function detectDoubleBookings(Collection $assignments): int
    {
        $created = 0;

        $grouped = $assignments
            ->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee !== null)
            ->groupBy(fn (ScheduleAssignment $assignment) => $assignment->effective_employee->id);

        foreach ($grouped as $employeeAssignments) {
            $sorted = $employeeAssignments->sortBy('start_at')->values();

            for ($i = 0; $i < $sorted->count(); $i++) {
                for ($j = $i + 1; $j < $sorted->count(); $j++) {
                    $first = $sorted[$i];
                    $second = $sorted[$j];

                    if (! $first->start_at->lt($second->end_at) || ! $second->start_at->lt($first->end_at)) {
                        continue;
                    }

                    $employee = $first->effective_employee;
                    $ids = collect([$first->id, $second->id])->sort()->values()->implode('-');
                    $fingerprint = $this->fingerprint('double_booking', $first->id, $first->schedule_date->toDateString(), $ids);

                    $created += $this->storeIfNew([
                        'schedule_assignment_id' => $first->id,
                        'department_id' => $first->department_id,
                        'user_id' => $employee?->id,
                        'conflict_date' => $first->schedule_date->toDateString(),
                        'type' => 'double_booking',
                        'severity' => 'error',
                        'title' => 'Double-booked employee',
                        'description' => "{$employee?->full_name} is assigned to overlapping shifts: {$first->shiftType->name} and {$second->shiftType->name}.",
                        'status' => 'open',
                        'source' => 'auto',
                        'fingerprint' => $fingerprint,
                    ]);
                }
            }
        }

        return $created;
    }

    private function detectWeeklyOvertime(Collection $assignments): int
    {
        $created = 0;

        $weeks = $assignments
            ->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee !== null && ! $assignment->is_standby)
            ->groupBy(fn (ScheduleAssignment $assignment) => $assignment->effective_employee->id.'|'.$assignment->schedule_date->copy()->startOfWeek()->toDateString());

        foreach ($weeks as $key => $weekAssignments) {
            /** @var ScheduleAssignment $first */
            $first = $weekAssignments->first();
            $employee = $first->effective_employee;
            $hours = round($weekAssignments->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2);

            if ($hours <= (float) $employee->max_weekly_hours) {
                continue;
            }

            [$employeeId, $weekStart] = explode('|', $key);
            $fingerprint = $this->fingerprint('weekly_overtime', (int) $employeeId, $weekStart, 'week');

            $created += $this->storeIfNew([
                'schedule_assignment_id' => $first->id,
                'department_id' => $first->department_id,
                'user_id' => $employee->id,
                'conflict_date' => $weekStart,
                'type' => 'weekly_overtime',
                'severity' => 'warning',
                'title' => 'Weekly hours exceed maximum',
                'description' => "{$employee->full_name} is scheduled for {$hours} net hours in the week starting {$first->schedule_date->copy()->startOfWeek()->format('M j, Y')}, above the {$employee->max_weekly_hours}-hour limit.",
                'status' => 'open',
                'source' => 'auto',
                'fingerprint' => $fingerprint,
            ]);
        }

        return $created;
    }

    private function persist(ScheduleAssignment $assignment, string $type, string $severity, Carbon $date, ?int $userId, string $title, string $description): int
    {
        return $this->storeIfNew([
            'schedule_assignment_id' => $assignment->id,
            'department_id' => $assignment->department_id,
            'user_id' => $userId,
            'conflict_date' => $date->toDateString(),
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'status' => 'open',
            'source' => 'auto',
            'fingerprint' => $this->fingerprint($type, $assignment->id, $date->toDateString()),
        ]);
    }

    private function storeIfNew(array $attributes): int
    {
        $existing = ScheduleConflict::query()->where('fingerprint', $attributes['fingerprint'])->first();

        if ($existing) {
            if ($existing->status === 'open') {
                $existing->fill([
                    'title' => Str::limit($attributes['title'], 250),
                    'description' => $attributes['description'],
                    'severity' => $attributes['severity'],
                ])->save();
            }

            return 0;
        }

        $attributes['title'] = Str::limit($attributes['title'], 250);
        $attributes['severity'] = in_array($attributes['severity'], array_keys(Axcess::CONFLICT_SEVERITIES), true)
            ? $attributes['severity']
            : 'warning';

        ScheduleConflict::create($attributes);

        return 1;
    }

    private function fingerprint(string $type, ?int $assignmentId, string $date, string $extra = ''): string
    {
        return sha1(implode('|', ['axcess', $type, $assignmentId ?? 'none', $date, $extra]));
    }
}
