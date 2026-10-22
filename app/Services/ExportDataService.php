<?php

namespace App\Services;

use App\Exports\Support\ExportOptions;
use App\Exports\Support\Formula;
use App\Models\Absence;
use App\Models\LeaveRequest;
use App\Models\ReplacementHistory;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Support\Axcess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExportDataService
{
    public function __construct(private readonly ReportService $reports) {}

    public function schedule(ExportOptions $options): array
    {
        $assignments = $this->scheduleQuery($options)
            ->with(['user.jobPosition', 'replacementUser', 'department', 'shiftType', 'breaks'])
            ->orderBy('start_at')
            ->get();

        $headings = [
            'Schedule Date', 'Day of Week', 'Employee ID', 'Employee Name', 'Department', 'Job Position',
            'Shift Type', 'Shift Start', 'Shift End', 'First Break Start', 'First Break End', 'Break Count',
            'Break Summary', 'Total Scheduled (hrs)', 'Total Break (hrs)', 'Net Working (hrs)',
            'Assignment Status', 'Standby Status', 'Replacement Employee', 'Overnight Shift',
        ];

        if ($options->includeSensitive) {
            $headings[] = 'Replacement Reason';
            $headings[] = 'Manager Notes';
        }

        $rows = $assignments->map(function (ScheduleAssignment $assignment) use ($options): array {
            $firstBreak = $assignment->first_break;
            $breakSummary = $assignment->breaks
                ->map(fn ($break) => $break->start_at->format('H:i').'–'.$break->end_at->format('H:i').($break->label ? ' '.$break->label : ''))
                ->implode('; ');

            $row = [
                $assignment->schedule_date->copy(),
                $assignment->schedule_date->format('l'),
                $assignment->user?->employee_id ?? '',
                $assignment->user?->full_name ?? 'Unassigned',
                $assignment->department->name,
                $assignment->user?->jobPosition?->title ?? '',
                $assignment->shiftType->name,
                $assignment->start_at->copy(),
                $assignment->end_at->copy(),
                $firstBreak?->start_at?->copy(),
                $firstBreak?->end_at?->copy(),
                $assignment->break_count,
                $breakSummary,
                $assignment->scheduled_hours,
                $assignment->break_hours,
                $assignment->net_hours,
                $assignment->status_label,
                $assignment->is_standby ? $assignment->standby_status_label : '—',
                $assignment->replacement_label ?? '',
                $assignment->is_overnight ? 'Yes' : 'No',
            ];

            if ($options->includeSensitive) {
                $row[] = $assignment->replacement_reason ?? '';
                $row[] = $assignment->notes ?? '';
            }

            return $row;
        })->all();

        $formats = [
            'A' => 'yyyy-mm-dd',
            'H' => 'dd-mmm-yyyy hh:mm',
            'I' => 'dd-mmm-yyyy hh:mm',
            'J' => 'dd-mmm-yyyy hh:mm',
            'K' => 'dd-mmm-yyyy hh:mm',
            'L' => '0',
            'N' => '0.00',
            'O' => '0.00',
            'P' => '0.00',
        ];

        $widths = [
            'A' => 12, 'B' => 11, 'C' => 12, 'D' => 22, 'E' => 18, 'F' => 20, 'G' => 16,
            'H' => 19, 'I' => 19, 'J' => 19, 'K' => 19, 'L' => 9, 'M' => 36, 'N' => 12,
            'O' => 11, 'P' => 12, 'Q' => 13, 'R' => 15, 'S' => 26, 'T' => 10, 'U' => 28, 'V' => 45,
        ];

        return $this->sheetData($headings, $rows, $formats, $widths, $options->includeSensitive ? [12, 20, 21] : [12]);
    }

    public function breaks(ExportOptions $options): array
    {
        $assignments = $this->scheduleQuery($options)
            ->with(['user', 'department', 'shiftType', 'breaks'])
            ->orderBy('start_at')
            ->get();

        $headings = [
            'Schedule Date', 'Day of Week', 'Employee ID', 'Employee Name', 'Department', 'Shift Type',
            'Break #', 'Break Label', 'Break Start', 'Break End', 'Duration (mins)', 'Duration (hrs)',
            'Shift Start', 'Shift End',
        ];

        $rows = [];

        foreach ($assignments as $assignment) {
            foreach ($assignment->breaks->sortBy('start_at')->values() as $index => $break) {
                $rows[] = [
                    $assignment->schedule_date->copy(),
                    $assignment->schedule_date->format('l'),
                    $assignment->user?->employee_id ?? '',
                    $assignment->user?->full_name ?? 'Unassigned',
                    $assignment->department->name,
                    $assignment->shiftType->name,
                    $index + 1,
                    $break->label ?? 'Break',
                    $break->start_at->copy(),
                    $break->end_at->copy(),
                    $break->duration_minutes,
                    $break->duration_hours,
                    $assignment->start_at->copy(),
                    $assignment->end_at->copy(),
                ];
            }
        }

        return $this->sheetData($headings, $rows, [
            'A' => 'yyyy-mm-dd',
            'I' => 'dd-mmm-yyyy hh:mm',
            'J' => 'dd-mmm-yyyy hh:mm',
            'K' => '0',
            'L' => '0.00',
            'M' => 'dd-mmm-yyyy hh:mm',
            'N' => 'dd-mmm-yyyy hh:mm',
        ], [
            'A' => 12, 'B' => 11, 'C' => 12, 'D' => 22, 'E' => 18, 'F' => 16, 'G' => 8,
            'H' => 18, 'I' => 19, 'J' => 19, 'K' => 10, 'L' => 10, 'M' => 19, 'N' => 19,
        ]);
    }

    public function staffSummary(ExportOptions $options): array
    {
        $summary = $this->reports->staffSummary($options->start, $options->end, $options->departmentId, $options->userId);

        $headings = [
            'Employee ID', 'Employee Name', 'Department', 'Job Position', 'Employment Status',
            'Scheduled Shifts', 'Scheduled Hours', 'Break Hours', 'Net Scheduled Hours',
            'Standby Assignments', 'Standby Hours', 'Off Days', 'Approved Leave Days',
            'Recorded Absences', 'Replacements Covered',
        ];

        $rows = $summary->map(fn (array $row): array => [
            $row['user']->employee_id,
            $row['user']->full_name,
            $row['user']->department?->name ?? '',
            $row['user']->jobPosition?->title ?? '',
            Axcess::label('employment_status', $row['user']->employment_status),
            $row['shift_count'],
            $row['scheduled_hours'],
            $row['break_hours'],
            $row['net_hours'],
            $row['standby_count'],
            $row['standby_hours'],
            $row['off_days'],
            $row['leave_days'],
            $row['absence_count'],
            $row['replacement_count'],
        ])->all();

        if ($rows !== []) {
            $first = 7;
            $last = 6 + count($rows);
            $rows[] = [
                'TOTAL', '', '', '', '',
                new Formula("=SUM(F{$first}:F{$last})"),
                new Formula("=SUM(G{$first}:G{$last})"),
                new Formula("=SUM(H{$first}:H{$last})"),
                new Formula("=SUM(I{$first}:I{$last})"),
                new Formula("=SUM(J{$first}:J{$last})"),
                new Formula("=SUM(K{$first}:K{$last})"),
                new Formula("=SUM(L{$first}:L{$last})"),
                new Formula("=SUM(M{$first}:M{$last})"),
                new Formula("=SUM(N{$first}:N{$last})"),
                new Formula("=SUM(O{$first}:O{$last})"),
            ];
        }

        return $this->sheetData($headings, $rows, [
            'F' => '0', 'G' => '0.00', 'H' => '0.00', 'I' => '0.00', 'J' => '0', 'K' => '0.00',
            'L' => '0', 'M' => '0.0', 'N' => '0', 'O' => '0',
        ], [
            'A' => 12, 'B' => 22, 'C' => 18, 'D' => 20, 'E' => 14, 'F' => 10, 'G' => 11,
            'H' => 11, 'I' => 13, 'J' => 12, 'K' => 11, 'L' => 9, 'M' => 13, 'N' => 12, 'O' => 13,
        ]);
    }

    public function leaveAbsences(ExportOptions $options): array
    {
        $leaves = LeaveRequest::query()
            ->with(['user.department', 'approver'])
            ->overlappingPeriod($options->start, $options->end)
            ->when($options->departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $options->departmentId)))
            ->when($options->userId, fn ($query) => $query->where('user_id', $options->userId))
            ->when($options->leaveType, fn ($query) => $query->where('leave_type', $options->leaveType))
            ->when($options->leaveStatus, fn ($query) => $query->where('status', $options->leaveStatus))
            ->orderBy('start_date')
            ->get();

        $userIds = $leaves->pluck('user_id')->unique()->values();
        $absences = collect();

        if ($options->includeAbsences) {
            $absences = Absence::query()
                ->with(['user.department', 'recorder'])
                ->whereDate('absence_date', '>=', $options->start->toDateString())
                ->whereDate('absence_date', '<=', $options->end->toDateString())
                ->when($options->departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $options->departmentId)))
                ->when($options->userId, fn ($query) => $query->where('user_id', $options->userId))
                ->orderBy('absence_date')
                ->get();

            $userIds = $userIds->merge($absences->pluck('user_id'))->unique()->values();
        }

        $scheduledDates = ScheduleAssignment::query()
            ->whereIn('user_id', $userIds)
            ->inPeriod($options->start, $options->end)
            ->notCancelled()
            ->get(['user_id', 'schedule_date', 'start_at'])
            ->groupBy('user_id');

        $headings = [
            'Record Type', 'Employee ID', 'Employee Name', 'Department', 'Leave / Absence Type',
            'Start Date', 'End Date', 'Duration (days)', 'Approval Status', 'Approver',
            'Approval Timestamp', 'Scheduling Conflicts',
        ];

        if ($options->includeSensitive) {
            $headings[] = 'Reason';
            $headings[] = 'Supporting Information';
            $headings[] = 'Notes';
        }

        $rows = [];

        foreach ($leaves as $leave) {
            $conflicts = $this->conflictingDates($scheduledDates->get($leave->user_id, collect()), $leave->start_date, $leave->end_date);
            $row = [
                'Leave',
                $leave->user->employee_id,
                $leave->user->full_name,
                $leave->user->department?->name ?? '',
                $leave->leave_type_label,
                $leave->start_date->copy(),
                $leave->end_date->copy(),
                $leave->duration_days,
                $leave->status_label,
                $leave->approver?->full_name ?? '',
                $leave->approved_at?->copy(),
                $conflicts,
            ];

            if ($options->includeSensitive) {
                $row[] = $leave->reason ?? '';
                $row[] = $leave->supporting_information ?? '';
                $row[] = $leave->rejection_reason ? 'Rejection: '.$leave->rejection_reason : '';
            }

            $rows[] = ['sort' => $leave->start_date->timestamp, 'row' => $row];
        }

        foreach ($absences as $absence) {
            $conflicts = $this->conflictingDates($scheduledDates->get($absence->user_id, collect()), $absence->absence_date, $absence->absence_date);
            $row = [
                'Absence',
                $absence->user->employee_id,
                $absence->user->full_name,
                $absence->user->department?->name ?? '',
                $absence->type_label,
                $absence->absence_date->copy(),
                $absence->absence_date->copy(),
                1,
                'Recorded',
                $absence->recorder?->full_name ?? '',
                $absence->created_at?->copy(),
                $conflicts,
            ];

            if ($options->includeSensitive) {
                $row[] = '';
                $row[] = '';
                $row[] = $absence->notes ?? '';
            }

            $rows[] = ['sort' => $absence->absence_date->timestamp, 'row' => $row];
        }

        usort($rows, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return $this->sheetData($headings, array_column($rows, 'row'), [
            'F' => 'yyyy-mm-dd',
            'G' => 'yyyy-mm-dd',
            'H' => '0.0',
            'K' => 'dd-mmm-yyyy hh:mm',
        ], [
            'A' => 11, 'B' => 12, 'C' => 22, 'D' => 18, 'E' => 18, 'F' => 12, 'G' => 12,
            'H' => 11, 'I' => 12, 'J' => 20, 'K' => 19, 'L' => 36, 'M' => 35, 'N' => 35, 'O' => 35,
        ], $options->includeSensitive ? [11, 12, 13, 14] : [11]);
    }

    public function standbyReplacements(ExportOptions $options): array
    {
        $standby = ScheduleAssignment::query()
            ->with(['user', 'department', 'shiftType'])
            ->standby()
            ->inPeriod($options->start, $options->end)
            ->notCancelled()
            ->when($options->departmentId, fn ($query) => $query->where('department_id', $options->departmentId))
            ->when($options->userId, fn ($query) => $query->where(function ($scope) use ($options): void {
                $scope->where('user_id', $options->userId)->orWhere('replacement_user_id', $options->userId);
            }))
            ->orderBy('start_at')
            ->get();

        $history = ReplacementHistory::query()
            ->with(['scheduleAssignment.department', 'scheduleAssignment.shiftType', 'originalUser', 'replacementUser', 'replacedBy'])
            ->whereHas('scheduleAssignment', function ($query) use ($options): void {
                $query->inPeriod($options->start, $options->end)
                    ->when($options->departmentId, fn ($scope) => $scope->where('department_id', $options->departmentId))
                    ->when($options->userId, fn ($scope) => $scope->where(function ($inner) use ($options): void {
                        $inner->where('user_id', $options->userId)->orWhere('replacement_user_id', $options->userId);
                    }));
            })
            ->orderBy('replaced_at')
            ->get();

        $headings = [
            'Record Type', 'Date', 'Department', 'Employee ID', 'Employee Name', 'Shift Type',
            'Scheduled Start', 'Scheduled End', 'Standby Status', 'Replaced Employee',
            'Replacement Employee', 'Replacement Reason', 'Recorded By', 'Recorded At',
        ];

        $rows = [];

        foreach ($standby as $assignment) {
            $rows[] = [
                'Standby',
                $assignment->schedule_date->copy(),
                $assignment->department->name,
                $assignment->user?->employee_id ?? '',
                $assignment->user?->full_name ?? 'Unassigned',
                $assignment->shiftType->name,
                $assignment->start_at->copy(),
                $assignment->end_at->copy(),
                $assignment->standby_status_label,
                $assignment->replacementUser?->full_name ?? '',
                $assignment->replacement_label ?? '',
                $assignment->replacement_reason ?? '',
                $assignment->createdBy?->full_name ?? '',
                $assignment->replaced_at?->copy(),
            ];
        }

        $coveredAssignmentIds = $history->pluck('schedule_assignment_id')->unique();

        foreach ($history as $record) {
            $assignment = $record->scheduleAssignment;
            $rows[] = [
                'Replacement',
                $assignment->schedule_date->copy(),
                $assignment->department->name,
                $record->originalUser?->employee_id ?? $assignment->user?->employee_id ?? '',
                $record->originalUser?->full_name ?? $assignment->user?->full_name ?? 'Unassigned',
                $assignment->shiftType->name,
                $assignment->start_at->copy(),
                $assignment->end_at->copy(),
                '—',
                $record->originalUser?->full_name ?? $assignment->user?->full_name ?? 'Unassigned',
                "{$record->replacementUser->employee_id} — {$record->replacementUser->full_name}",
                $record->reason ?? '',
                $record->replacedBy?->full_name ?? '',
                $record->replaced_at->copy(),
            ];
        }

        // Include current replacements that were entered without a history row.
        $currentReplacements = ScheduleAssignment::query()
            ->with(['user', 'replacementUser', 'department', 'shiftType', 'createdBy'])
            ->inPeriod($options->start, $options->end)
            ->notCancelled()
            ->whereNotNull('replacement_user_id')
            ->when($options->departmentId, fn ($query) => $query->where('department_id', $options->departmentId))
            ->whereNotIn('id', $coveredAssignmentIds)
            ->get();

        foreach ($currentReplacements as $assignment) {
            $rows[] = [
                'Replacement',
                $assignment->schedule_date->copy(),
                $assignment->department->name,
                $assignment->user?->employee_id ?? '',
                $assignment->user?->full_name ?? 'Unassigned',
                $assignment->shiftType->name,
                $assignment->start_at->copy(),
                $assignment->end_at->copy(),
                '—',
                $assignment->user?->full_name ?? 'Unassigned',
                $assignment->replacement_label,
                $assignment->replacement_reason ?? '',
                $assignment->createdBy?->full_name ?? '',
                $assignment->replaced_at?->copy(),
            ];
        }

        usort($rows, fn (array $a, array $b) => ($a[1]?->timestamp ?? 0) <=> ($b[1]?->timestamp ?? 0));

        return $this->sheetData($headings, $rows, [
            'B' => 'yyyy-mm-dd',
            'G' => 'dd-mmm-yyyy hh:mm',
            'H' => 'dd-mmm-yyyy hh:mm',
            'N' => 'dd-mmm-yyyy hh:mm',
        ], [
            'A' => 12, 'B' => 12, 'C' => 18, 'D' => 12, 'E' => 22, 'F' => 16, 'G' => 19,
            'H' => 19, 'I' => 14, 'J' => 22, 'K' => 26, 'L' => 32, 'M' => 20, 'N' => 19,
        ], [11, 12]);
    }

    public function conflicts(ExportOptions $options): array
    {
        $conflicts = $this->reports->conflicts($options->start, $options->end, $options->departmentId);

        $headings = [
            'Date', 'Day', 'Severity', 'Type', 'Employee ID', 'Employee Name', 'Department',
            'Shift Type', 'Shift Start', 'Shift End', 'Description', 'Status', 'Detected At',
            'Resolved By', 'Resolved At',
        ];

        $rows = $conflicts->map(fn ($conflict): array => [
            $conflict->conflict_date->copy(),
            $conflict->conflict_date->format('l'),
            $conflict->severity_label,
            $conflict->type_label,
            $conflict->user?->employee_id ?? '',
            $conflict->user?->full_name ?? '',
            $conflict->department?->name ?? '',
            $conflict->scheduleAssignment?->shiftType?->name ?? '',
            $conflict->scheduleAssignment?->start_at?->copy(),
            $conflict->scheduleAssignment?->end_at?->copy(),
            $conflict->description ?? '',
            $conflict->status_label,
            $conflict->created_at?->copy(),
            $conflict->resolver?->full_name ?? '',
            $conflict->resolved_at?->copy(),
        ])->all();

        return $this->sheetData($headings, $rows, [
            'A' => 'yyyy-mm-dd',
            'I' => 'dd-mmm-yyyy hh:mm',
            'J' => 'dd-mmm-yyyy hh:mm',
            'M' => 'dd-mmm-yyyy hh:mm',
            'O' => 'dd-mmm-yyyy hh:mm',
        ], [
            'A' => 12, 'B' => 11, 'C' => 10, 'D' => 24, 'E' => 12, 'F' => 22, 'G' => 18,
            'H' => 16, 'I' => 19, 'J' => 19, 'K' => 55, 'L' => 10, 'M' => 19, 'N' => 20, 'O' => 19,
        ], [10]);
    }

    public function staff(ExportOptions $options): array
    {
        $users = User::query()
            ->with(['department', 'jobPosition'])
            ->when($options->departmentId, fn ($query) => $query->where('department_id', $options->departmentId))
            ->when($options->employmentStatus, fn ($query) => $query->where('employment_status', $options->employmentStatus))
            ->when($options->standbyOnly, fn ($query) => $query->where('standby_eligible', true))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Job Position', 'Role', 'Employment Status',
            'Employment Type',
        ];

        if ($options->includeContacts) {
            $headings[] = 'Email';
            $headings[] = 'Phone';
        }

        $headings[] = 'Hire Date';
        $headings[] = 'Standby Eligible';
        $headings[] = 'Maximum Weekly Hours';
        $headings[] = 'Account Status';

        $rows = $users->map(function (User $user) use ($options): array {
            $row = [
                $user->employee_id,
                $user->full_name,
                $user->department?->name ?? '',
                $user->jobPosition?->title ?? '',
                $user->roleLabel(),
                Axcess::label('employment_status', $user->employment_status),
                Axcess::label('employment_type', $user->employment_type),
            ];

            if ($options->includeContacts) {
                $row[] = $user->email;
                $row[] = $user->phone ?? '';
            }

            $row[] = $user->hire_date?->copy();
            $row[] = $user->standby_eligible ? 'Yes' : 'No';
            $row[] = (float) $user->max_weekly_hours;
            $row[] = $user->is_active ? 'Active' : 'Inactive';

            return $row;
        })->all();

        $hireDateColumn = $options->includeContacts ? 'J' : 'H';
        $maxHoursColumn = $options->includeContacts ? 'L' : 'J';

        return $this->sheetData($headings, $rows, [
            $hireDateColumn => 'yyyy-mm-dd',
            $maxHoursColumn => '0.00',
        ], [
            'A' => 12, 'B' => 22, 'C' => 18, 'D' => 20, 'E' => 10, 'F' => 14, 'G' => 14,
            'H' => 26, 'I' => 17, 'J' => 12, 'K' => 11, 'L' => 13, 'M' => 12,
        ], [7, 8]);
    }

    public function overview(ExportOptions $options): array
    {
        $metrics = $this->reports->metrics($options->start, $options->end, $options->departmentId);

        $headings = ['Metric', 'Value', 'Notes'];
        $rows = [
            ['Total staff', $metrics['total_staff'], 'Active employees and managers in scope'],
            ['Staff scheduled', $metrics['staff_scheduled'], 'Distinct employees with at least one shift'],
            ['Shift assignments', $metrics['assignment_count'], 'Includes standby, excludes cancelled'],
            ['Scheduled shifts', $metrics['shift_count'], 'Non-standby shifts'],
            ['Scheduled hours', $metrics['scheduled_hours'], 'Planned hours including breaks; not attendance or payroll'],
            ['Break hours', $metrics['break_hours'], 'Total scheduled break time'],
            ['Net scheduled working hours', $metrics['net_hours'], 'Scheduled shift hours minus breaks'],
            ['Standby assignments', $metrics['standby_count'], 'Standby duties in the selected period'],
            ['Standby hours', $metrics['standby_hours'], 'Planned standby hours'],
            ['Unassigned shifts', $metrics['unassigned_shifts'], 'Shifts with no employee or replacement'],
            ['Coverage gaps', $metrics['coverage_gaps'], 'Department-days below the required minimum'],
            ['Approved leave days', $metrics['approved_leave_days'], 'Approved leave overlapping this period'],
            ['Pending leave requests', $metrics['pending_leave_requests'], 'Requests awaiting a decision'],
            ['Recorded absences', $metrics['absences'], 'Absence records in this period'],
            ['Open scheduling conflicts', $metrics['open_conflicts'], 'Errors and warnings requiring attention'],
            ['Break coverage issues', $metrics['missing_breaks'], 'Shifts with missing or insufficient breaks'],
            ['Replacements recorded', $metrics['replacements'], 'Replacement history entries'],
            ['Data note', '', 'Scheduled hours are planned hours. They are not actual attendance or payroll hours.'],
        ];

        return $this->sheetData($headings, $rows, [], ['A' => 30, 'B' => 14, 'C' => 64], [2]);
    }

    public function coverage(ExportOptions $options): array
    {
        $rows = $this->reports->coverageRows($options->start, $options->end, $options->departmentId)
            ->map(fn (array $row): array => [
                $row['date'],
                $row['day'],
                $row['department'],
                $row['required'],
                $row['scheduled'],
                $row['unassigned'],
                $row['gap'],
                $row['coverage_percent'],
                $row['status'],
            ])->all();

        return $this->sheetData([
            'Date', 'Day', 'Department', 'Required Staff', 'Scheduled Staff', 'Unassigned Shifts',
            'Coverage Gap', 'Coverage %', 'Status',
        ], $rows, [
            'A' => 'yyyy-mm-dd',
            'D' => '0',
            'E' => '0',
            'F' => '0',
            'G' => '0',
            'H' => '0.0%',
        ], [
            'A' => 12, 'B' => 11, 'C' => 20, 'D' => 12, 'E' => 12, 'F' => 13, 'G' => 12, 'H' => 11, 'I' => 14,
        ]);
    }

    public function breakCoverage(ExportOptions $options): array
    {
        $rows = $this->reports->breakCoverageRows($options->start, $options->end, $options->departmentId)
            ->map(fn (array $row): array => [
                $row['date'],
                $row['employee_id'] ?? '',
                $row['employee_name'],
                $row['department'],
                $row['shift_type'],
                $row['shift_hours'],
                $row['break_count'],
                $row['break_hours'],
                $row['break_percent'],
                $row['status'],
                $row['detail'],
            ])->all();

        return $this->sheetData([
            'Date', 'Employee ID', 'Employee Name', 'Department', 'Shift Type', 'Shift Hours',
            'Break Count', 'Break Hours', 'Break % of Shift', 'Break Coverage', 'Detail',
        ], $rows, [
            'A' => 'yyyy-mm-dd',
            'F' => '0.00',
            'G' => '0',
            'H' => '0.00',
            'I' => '0.0%',
        ], [
            'A' => 12, 'B' => 12, 'C' => 22, 'D' => 18, 'E' => 16, 'F' => 11, 'G' => 9,
            'H' => 11, 'I' => 11, 'J' => 14, 'K' => 55,
        ], [10]);
    }

    public function unassigned(ExportOptions $options): array
    {
        $assignments = $this->reports->unassignedRows($options->start, $options->end, $options->departmentId);

        $headings = ['Date', 'Day', 'Department', 'Shift Type', 'Shift Start', 'Shift End', 'Status', 'Standby'];

        if ($options->includeSensitive) {
            $headings[] = 'Manager Notes';
        }

        $rows = $assignments->map(function (ScheduleAssignment $assignment) use ($options): array {
            $row = [
                $assignment->schedule_date->copy(),
                $assignment->schedule_date->format('l'),
                $assignment->department->name,
                $assignment->shiftType->name,
                $assignment->start_at->copy(),
                $assignment->end_at->copy(),
                $assignment->status_label,
                $assignment->is_standby ? 'Yes' : 'No',
            ];

            if ($options->includeSensitive) {
                $row[] = $assignment->notes ?? '';
            }

            return $row;
        })->all();

        return $this->sheetData($headings, $rows, [
            'A' => 'yyyy-mm-dd',
            'E' => 'dd-mmm-yyyy hh:mm',
            'F' => 'dd-mmm-yyyy hh:mm',
        ], [
            'A' => 12, 'B' => 11, 'C' => 20, 'D' => 18, 'E' => 19, 'F' => 19, 'G' => 12, 'H' => 9, 'I' => 45,
        ], $options->includeSensitive ? [8] : []);
    }

    public function replacementHistory(ExportOptions $options): array
    {
        $history = $this->reports->replacementRows($options->start, $options->end, $options->departmentId);

        $rows = $history->map(function (ReplacementHistory $record): array {
            $assignment = $record->scheduleAssignment;

            return [
                $assignment->schedule_date->copy(),
                $assignment->department->name,
                $assignment->shiftType->name,
                $record->originalUser
                    ? "{$record->originalUser->employee_id} — {$record->originalUser->full_name}"
                    : ($assignment->user ? "{$assignment->user->employee_id} — {$assignment->user->full_name}" : 'Unassigned'),
                "{$record->replacementUser->employee_id} — {$record->replacementUser->full_name}",
                $record->replacedBy?->full_name ?? '',
                $record->replaced_at->copy(),
                $record->reason ?? '',
            ];
        })->all();

        return $this->sheetData([
            'Date', 'Department', 'Shift Type', 'Original Employee', 'Replacement Employee',
            'Replaced By', 'Replaced At', 'Reason',
        ], $rows, [
            'A' => 'yyyy-mm-dd',
            'G' => 'dd-mmm-yyyy hh:mm',
        ], [
            'A' => 12, 'B' => 18, 'C' => 16, 'D' => 28, 'E' => 28, 'F' => 20, 'G' => 19, 'H' => 40,
        ], [7]);
    }

    public function leaveSummary(ExportOptions $options): array
    {
        $leaves = LeaveRequest::query()
            ->with('user')
            ->overlappingPeriod($options->start, $options->end)
            ->when($options->departmentId, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department_id', $options->departmentId)))
            ->when($options->userId, fn ($query) => $query->where('user_id', $options->userId))
            ->when($options->leaveType, fn ($query) => $query->where('leave_type', $options->leaveType))
            ->when($options->leaveStatus, fn ($query) => $query->where('status', $options->leaveStatus))
            ->get();

        $grouped = $leaves->groupBy(fn (LeaveRequest $leave) => $leave->leave_type.'|'.$leave->status)
            ->map(function (Collection $group): array {
                /** @var LeaveRequest $first */
                $first = $group->first();

                return [
                    $first->leave_type_label,
                    $first->status_label,
                    $group->count(),
                    round($group->sum('duration_days'), 1),
                ];
            })
            ->sortBy([0, 1])
            ->values();

        $rows = $grouped->all();

        if ($rows !== []) {
            $first = 7;
            $last = 6 + count($rows);
            $rows[] = [
                'TOTAL', '',
                new Formula("=SUM(C{$first}:C{$last})"),
                new Formula("=SUM(D{$first}:D{$last})"),
            ];
        }

        return $this->sheetData(['Leave Type', 'Approval Status', 'Requests', 'Total Days'], $rows, [
            'C' => '0',
            'D' => '0.0',
        ], ['A' => 24, 'B' => 16, 'C' => 10, 'D' => 12]);
    }

    public function primaryRows(ExportOptions $options, string $type): array
    {
        return match ($type) {
            'staff' => $this->staff($options),
            'leave' => $this->leaveAbsences($options),
            'reports' => $this->overview($options),
            default => $this->schedule($options),
        };
    }

    public function scheduleQuery(ExportOptions $options)
    {
        return ScheduleAssignment::query()
            ->inPeriod($options->start, $options->end)
            ->when($options->departmentId, fn ($query) => $query->where('department_id', $options->departmentId))
            ->when($options->userId, fn ($query) => $query->where(function ($scope) use ($options): void {
                $scope->where('user_id', $options->userId)->orWhere('replacement_user_id', $options->userId);
            }))
            ->when($options->shiftTypeId, fn ($query) => $query->where('shift_type_id', $options->shiftTypeId))
            ->when($options->assignmentStatus, fn ($query) => $query->where('status', $options->assignmentStatus))
            ->when($options->standbyOnly, fn ($query) => $query->where('is_standby', true));
    }

    private function conflictingDates(Collection $scheduled, Carbon $from, Carbon $to): string
    {
        $dates = $scheduled
            ->filter(fn ($assignment) => $assignment->schedule_date->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay()))
            ->map(fn ($assignment) => $assignment->schedule_date->format('M j, Y'))
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 'None';
        }

        $label = $dates->take(3)->implode(', ');

        if ($dates->count() > 3) {
            $label .= ' +'.($dates->count() - 3).' more';
        }

        return $dates->count().' conflicting shift(s): '.$label;
    }

    private function sheetData(array $headings, array $rows, array $formats = [], array $widths = [], array $wrap = []): array
    {
        return [
            'headings' => $headings,
            'rows' => $rows,
            'formats' => $formats,
            'widths' => $widths,
            'wrap' => $wrap,
        ];
    }
}
