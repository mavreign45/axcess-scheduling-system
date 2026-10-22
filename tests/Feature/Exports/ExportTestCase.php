<?php

namespace Tests\Feature\Exports;

use App\Exports\Support\ExportOptions;
use App\Models\Department;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleBreak;
use App\Models\ShiftType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

abstract class ExportTestCase extends TestCase
{
    use RefreshDatabase;

    protected Carbon $periodStart;

    protected Carbon $periodEnd;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-22 12:00:00');

        $this->periodStart = Carbon::parse('2026-10-19')->startOfDay();
        $this->periodEnd = Carbon::parse('2026-10-25')->endOfDay();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function department(string $name = 'Operations', string $code = 'OPS'): Department
    {
        return Department::factory()->create([
            'name' => $name,
            'code' => $code,
            'min_staff_per_day' => 1,
        ]);
    }

    protected function user(
        Department $department,
        string $role = 'employee',
        string $employeeId = 'EMP-1001',
        string $firstName = 'Alex',
        string $lastName = 'Morgan',
        array $attributes = [],
    ): User {
        return User::factory()->create(array_merge([
            'department_id' => $department->id,
            'role' => $role,
            'employee_id' => $employeeId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($employeeId).'@axcess.test',
            'is_active' => true,
        ], $attributes));
    }

    protected function shiftType(string $name = 'Extended Night', string $start = '22:00:00', string $end = '08:00:00'): ShiftType
    {
        return ShiftType::factory()->create([
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'start_time' => $start,
            'end_time' => $end,
            'default_break_minutes' => 30,
        ]);
    }

    protected function assignment(
        User $user,
        Department $department,
        ShiftType $shiftType,
        string $date = '2026-10-19',
        string $start = '18:00',
        string $end = '08:00',
        array $attributes = [],
    ): ScheduleAssignment {
        $startAt = Carbon::parse($date.' '.$start);
        $endAt = Carbon::parse($date.' '.$end);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt->addDay();
        }

        return ScheduleAssignment::factory()->create(array_merge([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'shift_type_id' => $shiftType->id,
            'schedule_date' => $date,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'published',
            'is_standby' => false,
            'standby_status' => 'not_applicable',
        ], $attributes));
    }

    protected function addBreak(ScheduleAssignment $assignment, string $label, string $start, string $end, int $minutes = 30): ScheduleBreak
    {
        return ScheduleBreak::factory()->create([
            'schedule_assignment_id' => $assignment->id,
            'label' => $label,
            'start_at' => Carbon::parse($start),
            'end_at' => Carbon::parse($end),
            'duration_minutes' => $minutes,
        ]);
    }

    protected function exportOptions(array $overrides = []): ExportOptions
    {
        return new ExportOptions(
            start: $overrides['start'] ?? $this->periodStart->copy(),
            end: $overrides['end'] ?? $this->periodEnd->copy(),
            departmentId: $overrides['departmentId'] ?? null,
            userId: $overrides['userId'] ?? null,
            shiftTypeId: $overrides['shiftTypeId'] ?? null,
            assignmentStatus: $overrides['assignmentStatus'] ?? null,
            standbyOnly: $overrides['standbyOnly'] ?? false,
            employmentStatus: $overrides['employmentStatus'] ?? null,
            leaveType: $overrides['leaveType'] ?? null,
            leaveStatus: $overrides['leaveStatus'] ?? null,
            includeSensitive: $overrides['includeSensitive'] ?? false,
            includeContacts: $overrides['includeContacts'] ?? false,
            includeAbsences: $overrides['includeAbsences'] ?? true,
            worksheets: $overrides['worksheets'] ?? ['schedule'],
            reportType: $overrides['reportType'] ?? 'full',
            format: $overrides['format'] ?? 'xlsx',
            generatedBy: $overrides['generatedBy'] ?? null,
            filterLabels: $overrides['filterLabels'] ?? [],
        );
    }

    protected function workbookFrom(string $contents): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'axcess-xlsx-');
        file_put_contents($path, $contents);

        try {
            return IOFactory::createReader('Xlsx')->load($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function headings(Spreadsheet $workbook, string $sheetName, int $headerRow = 6): array
    {
        $sheet = $workbook->getSheetByName($sheetName);
        $this->assertNotNull($sheet, "Worksheet {$sheetName} was not found.");

        $headings = [];
        $lastColumn = $sheet->getHighestColumn();

        foreach ($sheet->getColumnIterator('A', $lastColumn) as $column) {
            $value = $sheet->getCell($column->getColumnIndex().$headerRow)->getValue();
            $headings[] = is_scalar($value) ? (string) $value : '';
        }

        return $headings;
    }

    protected function rowFor(Spreadsheet $workbook, string $sheetName, int $nameColumnIndex, string $name, int $headerRow = 6): ?int
    {
        $sheet = $workbook->getSheetByName($sheetName);
        $this->assertNotNull($sheet);

        for ($row = $headerRow + 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell(Coordinate::stringFromColumnIndex($nameColumnIndex).$row)->getValue() === $name) {
                return $row;
            }
        }

        return null;
    }
}
