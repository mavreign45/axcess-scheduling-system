<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleConflict;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
    {
        $user = $request->user();
        $today = now()->startOfDay();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        if ($user->isEmployee()) {
            return $this->employeeDashboard($user, $today, $weekStart, $weekEnd);
        }

        $departmentId = $user->isManager() ? $user->department_id : null;

        $todayAssignments = ScheduleAssignment::query()
            ->with(['user', 'replacementUser', 'department', 'shiftType', 'breaks'])
            ->whereDate('schedule_date', $today->toDateString())
            ->notCancelled()
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('start_at')
            ->get();

        $metrics = $reports->metrics($weekStart, $weekEnd, $departmentId);
        $coverageGaps = $reports->coverageRows($weekStart, $weekEnd, $departmentId)
            ->where('gap', '>', 0)
            ->take(5);

        $pendingLeave = LeaveRequest::query()
            ->with('user.department')
            ->where('status', 'pending')
            ->when($departmentId, fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery->where('department_id', $departmentId)))
            ->latest()
            ->take(5)
            ->get();

        $openConflicts = ScheduleConflict::query()
            ->with(['user', 'department', 'scheduleAssignment.shiftType'])
            ->where('status', 'open')
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderByRaw("CASE severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->latest('conflict_date')
            ->take(5)
            ->get();

        $upcomingStandby = ScheduleAssignment::query()
            ->with(['user', 'replacementUser', 'shiftType', 'department'])
            ->standby()
            ->notCancelled()
            ->whereDate('schedule_date', '>=', $today->toDateString())
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('start_at')
            ->take(4)
            ->get();

        return view('dashboard', [
            'managerView' => true,
            'today' => $today,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'todayAssignments' => $todayAssignments,
            'metrics' => $metrics,
            'coverageGaps' => $coverageGaps,
            'pendingLeave' => $pendingLeave,
            'openConflicts' => $openConflicts,
            'upcomingStandby' => $upcomingStandby,
        ]);
    }

    private function employeeDashboard($user, Carbon $today, Carbon $weekStart, Carbon $weekEnd): View
    {
        $weekAssignments = ScheduleAssignment::query()
            ->with(['department', 'shiftType', 'breaks', 'replacementUser'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhere('replacement_user_id', $user->id);
            })
            ->inPeriod($weekStart, $weekEnd)
            ->notCancelled()
            ->orderBy('start_at')
            ->get();

        $todayAssignments = $weekAssignments->filter(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->isSameDay($today));

        $leaves = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->latest('start_date')
            ->take(5)
            ->get();

        $stats = [
            'shifts_this_week' => $weekAssignments->where('is_standby', false)->count(),
            'hours_this_week' => round($weekAssignments->where('is_standby', false)->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2),
            'standby_this_week' => $weekAssignments->where('is_standby', true)->count(),
            'leave_days_this_year' => LeaveRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $today->year)
                ->sum('duration_days'),
        ];

        return view('dashboard', [
            'managerView' => false,
            'today' => $today,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'todayAssignments' => $todayAssignments,
            'weekAssignments' => $weekAssignments,
            'leaves' => $leaves,
            'stats' => $stats,
        ]);
    }
}
