<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StandbyController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        abort_unless(Gate::allows('manage-operations'), 403);

        $weekStart = $this->parseWeek($request->query('week'));
        $weekEnd = $weekStart->copy()->endOfWeek();
        $departmentId = $viewer->isManager() ? $viewer->department_id : $request->integer('department_id');
        $userId = $request->integer('user_id');
        $status = $request->query('standby_status');

        $assignments = ScheduleAssignment::query()
            ->with(['user', 'replacementUser', 'department', 'shiftType'])
            ->visibleTo($viewer)
            ->standby()
            ->inPeriod($weekStart, $weekEnd)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($userId, fn ($query) => $query->where(function ($scope) use ($userId): void {
                $scope->where('user_id', $userId)->orWhere('replacement_user_id', $userId);
            }))
            ->when($status, fn ($query) => $query->where('standby_status', $status))
            ->orderBy('start_at')
            ->get();

        $byDay = $assignments->groupBy(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString());

        return view('standby.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'assignments' => $assignments,
            'byDay' => $byDay,
            'departments' => $this->availableDepartments($viewer),
            'employees' => $this->availableEmployees($viewer),
            'stats' => [
                'total' => $assignments->count(),
                'available' => $assignments->where('standby_status', 'available')->count(),
                'called_in' => $assignments->where('standby_status', 'called_in')->count(),
                'hours' => round($assignments->sum(fn (ScheduleAssignment $assignment) => $assignment->scheduled_hours), 2),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ScheduleAssignment::class);
        $viewer = $request->user();

        $assignment = new ScheduleAssignment([
            'schedule_date' => $request->query('date', now()->toDateString()),
            'status' => 'published',
            'is_standby' => true,
            'standby_status' => 'available',
            'shift_type_id' => ShiftType::where('code', 'like', '%STANDBY%')->value('id'),
        ]);

        return view('schedule.form', [
            'assignment' => $assignment,
            'isEdit' => false,
            'standbyMode' => true,
            'departments' => $this->availableDepartments($viewer),
            'employees' => $this->availableEmployees($viewer),
            'shiftTypes' => ShiftType::where('is_active', true)->orderBy('start_time')->get(),
            'replacementCandidates' => collect(),
            'canChooseDepartment' => $viewer->isAdmin(),
        ]);
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_standby'] = true;

        if (($data['standby_status'] ?? 'not_applicable') === 'not_applicable') {
            $data['standby_status'] = 'available';
        }

        $assignment = $this->schedules->create($data, $request->user());

        return redirect()
            ->route('standby.index', ['week' => $assignment->schedule_date->startOfWeek()->toDateString()])
            ->with('success', 'Standby duty scheduled.');
    }

    public function updateStatus(Request $request, ScheduleAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $validated = $request->validate([
            'standby_status' => ['required', Rule::in(['available', 'called_in', 'released', 'completed'])],
        ]);

        abort_unless($assignment->is_standby, 404);

        $assignment->update([
            'standby_status' => $validated['standby_status'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Standby status updated.');
    }

    public function destroy(ScheduleAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);
        abort_unless($assignment->is_standby, 404);
        $week = $assignment->schedule_date->copy()->startOfWeek()->toDateString();
        $this->schedules->delete($assignment);

        return redirect()->route('standby.index', ['week' => $week])->with('success', 'Standby assignment removed.');
    }

    private function availableDepartments(User $viewer): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->when($viewer->isManager(), fn ($query) => $query->whereKey($viewer->department_id))
            ->orderBy('name')
            ->get();
    }

    private function availableEmployees(User $viewer): Collection
    {
        return User::query()
            ->with(['department', 'jobPosition'])
            ->active()
            ->whereIn('role', ['employee', 'manager'])
            ->where('standby_eligible', true)
            ->when($viewer->isManager(), fn ($query) => $query->where('department_id', $viewer->department_id))
            ->orderBy('first_name')
            ->get();
    }

    private function parseWeek(?string $week): Carbon
    {
        try {
            return Carbon::parse($week ?: now()->toDateString())->startOfWeek();
        } catch (\Throwable) {
            return now()->startOfWeek();
        }
    }
}
