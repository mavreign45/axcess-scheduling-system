<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\Absence;
use App\Models\Department;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\OffDay;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $viewer = $request->user();
        $search = trim((string) $request->query('search'));
        $status = $request->query('employment_status');
        $standby = $request->boolean('standby_eligible');
        $departmentId = $viewer->isManager() ? $viewer->department_id : $request->integer('department_id');

        $staff = User::query()
            ->with(['department', 'jobPosition'])
            ->visibleTo($viewer)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($scope) use ($search): void {
                    $scope->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query) => $query->where('employment_status', $status))
            ->when($standby, fn ($query) => $query->where('standby_eligible', true))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'departments' => $this->availableDepartments($viewer),
            'filters' => [
                'search' => $search,
                'employment_status' => $status,
                'standby_eligible' => $standby,
                'department_id' => $departmentId,
            ],
            'summary' => [
                'total' => User::visibleTo($viewer)->count(),
                'active' => User::visibleTo($viewer)->where('is_active', true)->count(),
                'standby' => User::visibleTo($viewer)->where('standby_eligible', true)->where('is_active', true)->count(),
                'departments' => $viewer->isAdmin()
                    ? Department::where('is_active', true)->count()
                    : 1,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);
        $viewer = $request->user();

        return view('staff.form', [
            'staffMember' => new User([
                'role' => 'employee',
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'max_weekly_hours' => 40,
                'standby_eligible' => true,
                'is_active' => true,
                'department_id' => $viewer->isManager() ? $viewer->department_id : null,
            ]),
            'isEdit' => false,
            'departments' => $this->availableDepartments($viewer),
            'positions' => $this->availablePositions($viewer),
            'canSetRole' => $viewer->isAdmin(),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $staff = User::create($data);

        return redirect()
            ->route('staff.show', $staff)
            ->with('success', 'Staff account created for '.$staff->full_name.'.');
    }

    public function show(Request $request, User $staff): View
    {
        Gate::authorize('view', $staff);
        $staff->load(['department', 'jobPosition']);

        $upcoming = ScheduleAssignment::query()
            ->with(['department', 'shiftType', 'breaks'])
            ->where(function ($query) use ($staff): void {
                $query->where('user_id', $staff->id)->orWhere('replacement_user_id', $staff->id);
            })
            ->whereDate('schedule_date', '>=', now()->toDateString())
            ->notCancelled()
            ->orderBy('start_at')
            ->take(10)
            ->get();

        $leaves = LeaveRequest::query()
            ->with('approver')
            ->where('user_id', $staff->id)
            ->latest('start_date')
            ->take(8)
            ->get();

        $absences = Absence::query()
            ->where('user_id', $staff->id)
            ->latest('absence_date')
            ->take(5)
            ->get();

        $offDays = OffDay::query()
            ->where('user_id', $staff->id)
            ->whereDate('date', '>=', now()->startOfMonth()->toDateString())
            ->orderBy('date')
            ->get();

        return view('staff.show', [
            'staffMember' => $staff,
            'upcoming' => $upcoming,
            'leaves' => $leaves,
            'absences' => $absences,
            'offDays' => $offDays,
        ]);
    }

    public function edit(Request $request, User $staff): View
    {
        Gate::authorize('update', $staff);
        $viewer = $request->user();

        return view('staff.form', [
            'staffMember' => $staff->load(['department', 'jobPosition']),
            'isEdit' => true,
            'departments' => $this->availableDepartments($viewer),
            'positions' => $this->availablePositions($viewer),
            'canSetRole' => $viewer->isAdmin(),
        ]);
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $staff->update($data);

        return redirect()
            ->route('staff.show', $staff)
            ->with('success', 'Staff account updated.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        Gate::authorize('delete', $staff);

        if ((int) $staff->id === (int) $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $staff->update([
            'is_active' => false,
            'employment_status' => 'inactive',
        ]);

        return redirect()->route('staff.index')->with('success', $staff->full_name.' has been deactivated. Historical schedules are preserved.');
    }

    private function availableDepartments(User $viewer)
    {
        return Department::query()
            ->where('is_active', true)
            ->when($viewer->isManager(), fn ($query) => $query->whereKey($viewer->department_id))
            ->orderBy('name')
            ->get();
    }

    private function availablePositions(User $viewer)
    {
        return JobPosition::query()
            ->with('department')
            ->where('is_active', true)
            ->when($viewer->isManager(), fn ($query) => $query->where('department_id', $viewer->department_id))
            ->orderBy('title')
            ->get();
    }
}
