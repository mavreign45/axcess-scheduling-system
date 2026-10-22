<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ScheduleConflict;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\ConflictDetector;
use App\Services\PeriodResolver;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ConflictDetector $detector,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('view-reports');
        $viewer = $request->user();
        $departmentId = $viewer->isManager() ? $viewer->department_id : ($request->integer('department_id') ?: null);

        try {
            [$start, $end] = PeriodResolver::resolve([
                'mode' => $request->query('mode', 'month'),
                'month' => $request->query('month', now()->format('Y-m')),
                'week' => $request->query('week', now()->toDateString()),
                'date' => $request->query('date', now()->toDateString()),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ]);
        } catch (InvalidArgumentException) {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        }

        $metrics = $this->reports->metrics($start, $end, $departmentId);
        $staffSummary = $this->reports->staffSummary($start, $end, $departmentId);
        $coverageGaps = $this->reports->coverageRows($start, $end, $departmentId)->where('gap', '>', 0);
        $breakIssues = $this->reports->breakCoverageRows($start, $end, $departmentId)->whereIn('status', ['Missing', 'Insufficient']);
        $conflicts = $this->reports->conflicts($start, $end, $departmentId);
        $unassigned = $this->reports->unassignedRows($start, $end, $departmentId);
        $replacements = $this->reports->replacementRows($start, $end, $departmentId);

        return view('reports.index', [
            'start' => $start,
            'end' => $end,
            'departmentId' => $departmentId,
            'departments' => $this->availableDepartments($viewer),
            'employees' => $this->availableEmployees($viewer),
            'shiftTypes' => ShiftType::where('is_active', true)->get(),
            'metrics' => $metrics,
            'staffSummary' => $staffSummary,
            'coverageGaps' => $coverageGaps,
            'coverageGapCount' => $coverageGaps->count(),
            'breakIssues' => $breakIssues,
            'breakIssueCount' => $breakIssues->count(),
            'conflicts' => $conflicts,
            'conflictCount' => $conflicts->count(),
            'unassigned' => $unassigned,
            'unassignedCount' => $unassigned->count(),
            'replacements' => $replacements,
            'replacementCount' => $replacements->count(),
        ]);
    }

    public function runConflicts(Request $request): RedirectResponse
    {
        Gate::authorize('view-reports');
        $viewer = $request->user();
        $departmentId = $viewer->isManager() ? $viewer->department_id : ($request->integer('department_id') ?: null);

        try {
            [$start, $end] = PeriodResolver::resolve([
                'mode' => $request->input('mode', 'month'),
                'month' => $request->input('month', now()->format('Y-m')),
                'week' => $request->input('week', now()->toDateString()),
                'date' => $request->input('date', now()->toDateString()),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $count = $this->detector->detect($start, $end, $departmentId);

        return back()->with('success', $count > 0
            ? "{$count} new scheduling conflict(s) detected."
            : 'Conflict check completed. No new conflicts were found.');
    }

    public function resolveConflict(Request $request, ScheduleConflict $conflict): RedirectResponse
    {
        $this->authorize('resolve', $conflict);

        $conflict->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Conflict marked as resolved.');
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
            ->visibleTo($viewer)
            ->whereIn('role', ['employee', 'manager'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();
    }
}
