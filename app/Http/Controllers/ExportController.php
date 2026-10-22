<?php

namespace App\Http\Controllers;

use App\Exports\CsvExport;
use App\Exports\LeaveWorkbookExport;
use App\Exports\ReportsWorkbookExport;
use App\Exports\ScheduleWorkbookExport;
use App\Exports\StaffWorkbookExport;
use App\Exports\Support\ExportOptions;
use App\Http\Requests\LeaveExportRequest;
use App\Http\Requests\ReportExportRequest;
use App\Http\Requests\ScheduleExportRequest;
use App\Http\Requests\StaffExportRequest;
use App\Models\Department;
use App\Models\ExportLog;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\ExportDataService;
use App\Support\Axcess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExportController extends Controller
{
    public function __construct(private readonly ExportDataService $data) {}

    public function schedule(ScheduleExportRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        return $this->handle($request, 'schedule', function () use ($request): array {
            $data = $request->validated();
            [$start, $end] = $request->resolvedPeriod();
            $departmentId = $this->effectiveDepartment($request, $data['department_id'] ?? null);
            $userId = $this->validatedUser($request, $data['user_id'] ?? null, $departmentId);
            $sensitive = (bool) ($data['include_sensitive'] ?? false);

            $options = new ExportOptions(
                start: $start,
                end: $end,
                departmentId: $departmentId,
                userId: $userId,
                shiftTypeId: $data['shift_type_id'] ?? null,
                assignmentStatus: $data['status'] ?? null,
                standbyOnly: (bool) ($data['standby_only'] ?? false),
                includeSensitive: $sensitive,
                worksheets: array_values(array_unique($data['worksheets'] ?? ['schedule'])),
                format: $data['format'],
                generatedBy: $request->user(),
                filterLabels: $this->filterLabels($data, $request),
            );

            return [$options, new ScheduleWorkbookExport($options, $this->data), 'Schedule'];
        });
    }

    public function staff(StaffExportRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        return $this->handle($request, 'staff', function () use ($request): array {
            $data = $request->validated();
            $departmentId = $this->effectiveDepartment($request, $data['department_id'] ?? null);

            $options = new ExportOptions(
                start: now()->startOfDay(),
                end: now()->endOfDay(),
                departmentId: $departmentId,
                employmentStatus: $data['employment_status'] ?? null,
                includeSensitive: (bool) ($data['include_sensitive'] ?? false),
                includeContacts: (bool) ($data['include_contacts'] ?? false),
                standbyOnly: (bool) ($data['standby_only'] ?? false),
                format: $data['format'],
                generatedBy: $request->user(),
                filterLabels: $this->filterLabels($data, $request),
            );

            return [$options, new StaffWorkbookExport($options, $this->data), 'Staff'];
        });
    }

    public function leave(LeaveExportRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        return $this->handle($request, 'leave', function () use ($request): array {
            $data = $request->validated();
            [$start, $end] = $request->resolvedPeriod();
            $departmentId = $this->effectiveDepartment($request, $data['department_id'] ?? null);
            $userId = $this->validatedUser($request, $data['user_id'] ?? null, $departmentId);

            $options = new ExportOptions(
                start: $start,
                end: $end,
                departmentId: $departmentId,
                userId: $userId,
                leaveType: $data['leave_type'] ?? null,
                leaveStatus: $data['leave_status'] ?? null,
                includeSensitive: (bool) ($data['include_sensitive'] ?? false),
                includeAbsences: (bool) ($data['include_absences'] ?? true),
                format: $data['format'],
                generatedBy: $request->user(),
                filterLabels: $this->filterLabels($data, $request),
            );

            return [$options, new LeaveWorkbookExport($options, $this->data), 'Leave'];
        });
    }

    public function reports(ReportExportRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        return $this->handle($request, 'reports', function () use ($request): array {
            $data = $request->validated();
            [$start, $end] = $request->resolvedPeriod();
            $departmentId = $this->effectiveDepartment($request, $data['department_id'] ?? null);
            $userId = $this->validatedUser($request, $data['user_id'] ?? null, $departmentId);

            $options = new ExportOptions(
                start: $start,
                end: $end,
                departmentId: $departmentId,
                userId: $userId,
                includeSensitive: (bool) ($data['include_sensitive'] ?? false),
                reportType: $data['report_type'] ?? 'full',
                format: $data['format'],
                generatedBy: $request->user(),
                filterLabels: $this->filterLabels($data, $request),
            );

            return [$options, new ReportsWorkbookExport($options, $this->data), 'Report'];
        });
    }

    /**
     * @param  callable(): array{0: ExportOptions, 1: object, 2: string}  $builder
     */
    private function handle(Request $request, string $type, callable $builder): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        try {
            [$options, $export, $fileType] = $builder();
            $fileName = $options->filename($fileType);
            $rowCount = null;

            if ($options->format === 'csv') {
                $primary = match ($type) {
                    'staff' => $this->data->staff($options),
                    'leave' => $this->data->leaveAbsences($options),
                    'reports' => $this->data->overview($options),
                    default => $this->data->schedule($options),
                };

                $rowCount = count($primary['rows']);
                $csv = new CsvExport($primary['headings'], $primary['rows'], 'Axcess '.$fileType.' export');
                $response = Excel::download($csv, $fileName, ExcelWriter::CSV);
            } else {
                $response = Excel::download($export, $fileName, ExcelWriter::XLSX);
            }

            $this->logExport($request, $type, $options, $fileName, $rowCount);

            return $response;
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $message = 'Axcess could not generate this export. Please check the selected filters and try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return back()->with('error', $message);
        }
    }

    private function effectiveDepartment(Request $request, ?int $requested): ?int
    {
        $user = $request->user();

        if ($user->isManager()) {
            if (! $user->department_id) {
                abort(403, 'Your manager account is not assigned to a department.');
            }

            if ($requested !== null && (int) $requested !== (int) $user->department_id) {
                abort(403, 'You can only export data for your own department.');
            }

            return (int) $user->department_id;
        }

        return $requested;
    }

    private function validatedUser(Request $request, ?int $userId, ?int $departmentId): ?int
    {
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        if (! $user) {
            throw ValidationException::withMessages(['user_id' => 'The selected employee could not be found.']);
        }

        if ($request->user()->isManager() && (int) $user->department_id !== (int) $request->user()->department_id) {
            throw ValidationException::withMessages(['user_id' => 'You can only export employees in your own department.']);
        }

        if ($departmentId && (int) $user->department_id !== $departmentId) {
            throw ValidationException::withMessages(['user_id' => 'The selected employee does not belong to the selected department.']);
        }

        return $userId;
    }

    private function filterLabels(array $data, Request $request): array
    {
        $labels = [];

        if (! empty($data['department_id']) || $request->user()->isManager()) {
            $departmentId = $data['department_id'] ?? $request->user()->department_id;
            $department = $departmentId ? Department::find($departmentId) : null;

            if ($department) {
                $labels[] = 'Department: '.$department->name;
            }
        }

        if (! empty($data['user_id'])) {
            $employee = User::find($data['user_id']);

            if ($employee) {
                $labels[] = 'Employee: '.$employee->full_name.' ('.$employee->employee_id.')';
            }
        }

        if (! empty($data['shift_type_id'])) {
            $shift = ShiftType::find($data['shift_type_id']);

            if ($shift) {
                $labels[] = 'Shift type: '.$shift->name;
            }
        }

        if (! empty($data['status'])) {
            $labels[] = 'Assignment status: '.Axcess::label('assignment_status', $data['status']);
        }

        if (! empty($data['standby_only'])) {
            $labels[] = 'Standby duties only';
        }

        if (! empty($data['employment_status'])) {
            $labels[] = 'Employment status: '.Axcess::label('employment_status', $data['employment_status']);
        }

        if (! empty($data['leave_type'])) {
            $labels[] = 'Leave type: '.Axcess::label('leave_type', $data['leave_type']);
        }

        if (! empty($data['leave_status'])) {
            $labels[] = 'Leave status: '.Axcess::label('leave_status', $data['leave_status']);
        }

        if (! empty($data['report_type'])) {
            $labels[] = 'Report: '.(Axcess::REPORT_TYPES[$data['report_type']] ?? $data['report_type']);
        }

        if (! empty($data['include_contacts'])) {
            $labels[] = 'Contact details included';
        }

        if (! empty($data['include_sensitive'])) {
            $labels[] = 'Confidential fields included';
        }

        return $labels;
    }

    private function logExport(Request $request, string $type, ExportOptions $options, string $fileName, ?int $rowCount): void
    {
        try {
            ExportLog::create([
                'user_id' => $request->user()->id,
                'type' => $type,
                'format' => $options->format,
                'file_name' => $fileName,
                'filters' => $options->logSummary(),
                'row_count' => $rowCount,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
