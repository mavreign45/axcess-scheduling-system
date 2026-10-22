<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplacementRequest;
use App\Http\Requests\ScheduleRequest;
use App\Models\Department;
use App\Models\ScheduleAssignment;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $weekStart = $this->parseWeek($request->query('week'));
        $weekEnd = $weekStart->copy()->endOfWeek();

        $query = $this->filteredQuery($request, $viewer, $weekStart, $weekEnd)
            ->with(['user', 'replacementUser', 'department', 'shiftType', 'breaks'])
            ->orderBy('start_at');

        $assignments = $query->get();
        $byDay = $assignments->groupBy(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString());

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDay): array {
            $date = $weekStart->copy()->addDays($offset);
            $dayAssignments = $byDay->get($date->toDateString(), collect());

            return [
                'date' => $date,
                'assignments' => $dayAssignments,
                'staff_count' => $dayAssignments->map(fn (ScheduleAssignment $assignment) => $assignment->effective_employee?->id)->filter()->unique()->count(),
                'hours' => round($dayAssignments->where('is_standby', false)->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2),
            ];
        });

        return view('schedule.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'assignments' => $assignments,
            'departments' => $this->availableDepartments($viewer),
            'employees' => $this->availableEmployees($viewer),
            'shiftTypes' => ShiftType::where('is_active', true)->orderBy('start_time')->get(),
            'stats' => $this->weekStats($assignments),
            'canManage' => $viewer->can('create', ScheduleAssignment::class),
        ]);
    }

    public function month(Request $request): View
    {
        $viewer = $request->user();
        $monthStart = $this->parseMonth($request->query('month'));
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek();
        $gridEnd = $monthEnd->copy()->endOfWeek();

        $assignments = $this->filteredQuery($request, $viewer, $monthStart, $monthEnd)
            ->with(['user', 'replacementUser', 'department', 'shiftType'])
            ->orderBy('start_at')
            ->get();

        $byDay = $assignments->groupBy(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString());

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->copy()->addDays($i);
                $dayAssignments = $byDay->get($date->toDateString(), collect());

                $week[] = [
                    'date' => $date,
                    'in_month' => $date->month === $monthStart->month,
                    'assignments' => $dayAssignments,
                    'staff_count' => $dayAssignments->map(fn (ScheduleAssignment $assignment) => $assignment->effective_employee?->id)->filter()->unique()->count(),
                ];
            }

            $weeks[] = $week;
            $cursor->addWeek();
        }

        return view('schedule.month', [
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'weeks' => $weeks,
            'assignments' => $assignments,
            'departments' => $this->availableDepartments($viewer),
            'employees' => $this->availableEmployees($viewer),
            'shiftTypes' => ShiftType::where('is_active', true)->orderBy('start_time')->get(),
            'stats' => $this->monthStats($assignments),
            'canManage' => $viewer->can('create', ScheduleAssignment::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ScheduleAssignment::class);
        $viewer = $request->user();

        $assignment = new ScheduleAssignment([
            'schedule_date' => $request->query('date', now()->toDateString()),
            'status' => 'published',
            'is_standby' => $request->boolean('standby'),
            'standby_status' => $request->boolean('standby') ? 'available' : 'not_applicable',
        ]);

        if ($request->filled('shift_type_id')) {
            $assignment->shift_type_id = (int) $request->query('shift_type_id');
        }

        return view('schedule.form', $this->formData($viewer, $assignment, false));
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $assignment = $this->schedules->create($request->validated(), $request->user());

        return redirect()
            ->route('schedule.index', ['week' => $assignment->schedule_date->startOfWeek()->toDateString()])
            ->with('success', 'Shift for '.$assignment->employee_name.' has been scheduled.');
    }

    public function edit(Request $request, ScheduleAssignment $assignment): View
    {
        $this->authorize('update', $assignment);
        $assignment->load(['user', 'replacementUser', 'breaks']);
        $viewer = $request->user();

        return view('schedule.form', $this->formData($viewer, $assignment, true));
    }

    public function update(ScheduleRequest $request, ScheduleAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment);
        $assignment = $this->schedules->update($assignment, $request->validated(), $request->user());

        return redirect()
            ->route('schedule.index', ['week' => $assignment->schedule_date->startOfWeek()->toDateString()])
            ->with('success', 'Shift updated successfully.');
    }

    public function destroy(ScheduleAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);
        $week = $assignment->schedule_date->copy()->startOfWeek()->toDateString();
        $this->schedules->delete($assignment);

        return redirect()->route('schedule.index', ['week' => $week])->with('success', 'Shift removed from the schedule.');
    }

    public function replace(ReplacementRequest $request, ScheduleAssignment $assignment): RedirectResponse
    {
        $replacement = User::findOrFail($request->integer('replacement_user_id'));
        $this->schedules->recordReplacement($assignment, $replacement, $request->input('replacement_reason'), $request->user());

        return back()->with('success', 'Replacement recorded. The schedule and replacement history have been updated.');
    }

    private function filteredQuery(Request $request, User $viewer, Carbon $start, Carbon $end)
    {
        $departmentId = $viewer->isManager() ? $viewer->department_id : $request->integer('department_id');
        $userId = $viewer->isEmployee() ? $viewer->id : $request->integer('user_id');
        $shiftTypeId = $request->integer('shift_type_id');
        $status = $request->query('status');

        return ScheduleAssignment::query()
            ->visibleTo($viewer)
            ->inPeriod($start, $end)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($userId, fn ($query) => $query->where(function ($scope) use ($userId): void {
                $scope->where('user_id', $userId)->orWhere('replacement_user_id', $userId);
            }))
            ->when($shiftTypeId, fn ($query) => $query->where('shift_type_id', $shiftTypeId))
            ->when($status, fn ($query) => $query->where('status', $status));
    }

    private function formData(User $viewer, ScheduleAssignment $assignment, bool $isEdit): array
    {
        $departments = $this->availableDepartments($viewer);
        $employees = $this->availableEmployees($viewer);

        $replacementCandidates = collect();

        if ($isEdit && $assignment->department_id) {
            $replacementCandidates = User::query()
                ->with('department')
                ->where('department_id', $assignment->department_id)
                ->where('is_active', true)
                ->where('id', '!=', $assignment->user_id)
                ->orderBy('first_name')
                ->get();
        }

        return [
            'assignment' => $assignment,
            'isEdit' => $isEdit,
            'departments' => $departments,
            'employees' => $employees,
            'shiftTypes' => ShiftType::where('is_active', true)->orderBy('start_time')->get(),
            'replacementCandidates' => $replacementCandidates,
            'canChooseDepartment' => $viewer->isAdmin(),
        ];
    }

    private function availableDepartments(User $viewer): Collection
    {
        if ($viewer->isEmployee()) {
            return collect();
        }

        return Department::query()
            ->where('is_active', true)
            ->when($viewer->isManager(), fn ($query) => $query->whereKey($viewer->department_id))
            ->orderBy('name')
            ->get();
    }

    private function availableEmployees(User $viewer): Collection
    {
        if ($viewer->isEmployee()) {
            return collect();
        }

        return User::query()
            ->with(['department', 'jobPosition'])
            ->active()
            ->whereIn('role', ['employee', 'manager'])
            ->when($viewer->isManager(), fn ($query) => $query->where('department_id', $viewer->department_id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function weekStats(Collection $assignments): array
    {
        $shifts = $assignments->where('is_standby', false);

        return [
            'assignments' => $assignments->count(),
            'shifts' => $shifts->count(),
            'net_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2),
            'standby' => $assignments->where('is_standby', true)->count(),
            'unassigned' => $assignments->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee === null)->count(),
            'staff' => $assignments->map(fn (ScheduleAssignment $assignment) => $assignment->effective_employee?->id)->filter()->unique()->count(),
        ];
    }

    private function monthStats(Collection $assignments): array
    {
        $shifts = $assignments->where('is_standby', false);

        return [
            'assignments' => $assignments->count(),
            'net_hours' => round($shifts->sum(fn (ScheduleAssignment $assignment) => $assignment->net_hours), 2),
            'standby' => $assignments->where('is_standby', true)->count(),
            'unassigned' => $assignments->filter(fn (ScheduleAssignment $assignment) => $assignment->effective_employee === null)->count(),
            'staff' => $assignments->map(fn (ScheduleAssignment $assignment) => $assignment->effective_employee?->id)->filter()->unique()->count(),
        ];
    }

    private function parseWeek(?string $week): Carbon
    {
        try {
            return Carbon::parse($week ?: now()->toDateString())->startOfWeek();
        } catch (\Throwable) {
            return now()->startOfWeek();
        }
    }

    private function parseMonth(?string $month): Carbon
    {
        try {
            return Carbon::parse(($month ?: now()->format('Y-m')).'-01')->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
