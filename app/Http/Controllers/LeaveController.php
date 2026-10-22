<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Mail\LeaveStatusMail;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\ConflictDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class LeaveController extends Controller
{
    public function __construct(private readonly ConflictDetector $conflicts) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $status = $request->query('status');
        $type = $request->query('leave_type');
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : null;
        $employeeId = $request->integer('user_id');

        $leaves = LeaveRequest::query()
            ->with(['user.department', 'approver'])
            ->visibleTo($viewer)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, fn ($query) => $query->where('leave_type', $type))
            ->when($employeeId, fn ($query) => $query->where('user_id', $employeeId))
            ->when($from, fn ($query) => $query->whereDate('end_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('start_date', '<=', $to->toDateString()))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        $base = LeaveRequest::query()->visibleTo($viewer);
        $approvedThisYear = (clone $base)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->sum('duration_days');

        return view('leave.index', [
            'leaves' => $leaves,
            'employees' => $this->availableEmployees($viewer),
            'stats' => [
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'approved_days' => round((float) $approvedThisYear, 1),
                'on_leave_today' => (clone $base)->where('status', 'approved')
                    ->whereDate('start_date', '<=', now()->toDateString())
                    ->whereDate('end_date', '>=', now()->toDateString())
                    ->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
            ],
            'filters' => [
                'status' => $status,
                'leave_type' => $type,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'user_id' => $employeeId,
            ],
            'canManage' => $viewer->canManageOperations(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LeaveRequest::class);
        $viewer = $request->user();

        $leave = new LeaveRequest([
            'user_id' => $viewer->isEmployee() ? $viewer->id : $request->integer('user_id'),
            'leave_type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        return view('leave.form', [
            'leave' => $leave,
            'employees' => $viewer->isEmployee() ? collect([$viewer]) : $this->availableEmployees($viewer),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        $leave = LeaveRequest::create([
            ...$validated,
            'duration_days' => LeaveRequest::calculateDuration($start, $end),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('leave.show', $leave)
            ->with('success', 'Leave request submitted and is awaiting approval.');
    }

    public function show(LeaveRequest $leave): View
    {
        $this->authorize('view', $leave);
        $leave->load(['user.department', 'approver']);

        $conflictingShifts = ScheduleAssignment::query()
            ->with(['shiftType', 'department'])
            ->where('user_id', $leave->user_id)
            ->whereDate('schedule_date', '>=', $leave->start_date->toDateString())
            ->whereDate('schedule_date', '<=', $leave->end_date->toDateString())
            ->notCancelled()
            ->orderBy('start_at')
            ->get();

        return view('leave.show', [
            'leave' => $leave,
            'conflictingShifts' => $conflictingShifts,
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        abort_unless($leave->status === 'pending', 422, 'Only pending leave requests can be approved.');

        $leave->update([
            'status' => 'approved',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->conflicts->detect(
            $leave->start_date->copy()->startOfWeek(),
            $leave->end_date->copy()->endOfWeek(),
            $leave->user->department_id,
        );

        $this->sendStatusMail($leave, 'approved');

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        abort_unless($leave->status === 'pending', 422, 'Only pending leave requests can be rejected.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $leave->update([
            'status' => 'rejected',
            'approver_id' => $request->user()->id,
            'approved_at' => null,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->sendStatusMail($leave, 'rejected', $validated['rejection_reason']);

        return back()->with('success', 'Leave request rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('cancel', $leave);

        $leave->update([
            'status' => 'cancelled',
            'approved_at' => null,
        ]);

        $this->sendStatusMail($leave, 'cancelled');

        return back()->with('success', 'Leave request cancelled.');
    }

    private function sendStatusMail(LeaveRequest $leave, string $status, ?string $note = null): void
    {
        try {
            $leave->loadMissing('user');
            Mail::to($leave->user->email)->send(new LeaveStatusMail($leave, $status, $note));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function availableEmployees(User $viewer)
    {
        if ($viewer->isEmployee()) {
            return collect([$viewer]);
        }

        return User::query()
            ->with(['department', 'jobPosition'])
            ->visibleTo($viewer)
            ->whereIn('role', ['employee', 'manager'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();
    }
}
