<?php

namespace Tests\Feature\Exports;

use App\Models\Department;
use App\Models\ExportLog;
use App\Models\ShiftType;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExportAuthorizationTest extends ExportTestCase
{
    public function test_guests_must_log_in_before_exporting(): void
    {
        foreach (['exports.schedule', 'exports.staff', 'exports.leave', 'exports.reports'] as $route) {
            $this->get(route($route, $this->query()))->assertRedirect(route('login'));
        }
    }

    public function test_employees_cannot_download_exports(): void
    {
        $department = $this->department();
        $employee = $this->user($department, employeeId: 'EMP-1001');

        foreach (['exports.schedule', 'exports.staff', 'exports.leave', 'exports.reports'] as $route) {
            $this->actingAs($employee)->get(route($route, $this->query()))->assertForbidden();
        }
    }

    public function test_manager_exports_are_limited_to_their_own_department(): void
    {
        [$ops, $sales, $manager, $opsEmployee, $salesEmployee, $shift] = $this->departmentsAndPeople();
        $this->assignment($opsEmployee, $ops, $shift, '2026-10-19', '08:00', '16:00');
        $this->assignment($salesEmployee, $sales, $shift, '2026-10-20', '08:00', '16:00');

        $response = $this->actingAs($manager)->get(route('exports.schedule', $this->query()));
        $response->assertOk();
        $response->assertDownload('Axcess_Schedule_2026-10-19_to_2026-10-25.xlsx');

        $workbook = $this->responseWorkbook($response);
        $schedule = $workbook->getSheetByName('Schedule');

        $this->assertSame(7, $schedule->getHighestRow());
        $this->assertSame('Owen Park', $schedule->getCell('D7')->getValue());
        $this->assertStringNotContainsString('Sara Sales', $this->allText($workbook));
        $this->assertDatabaseHas('export_logs', [
            'user_id' => $manager->id,
            'type' => 'schedule',
            'format' => 'xlsx',
            'file_name' => 'Axcess_Schedule_2026-10-19_to_2026-10-25.xlsx',
        ]);
    }

    public function test_manager_cannot_request_another_department_or_employee(): void
    {
        [$ops, $sales, $manager] = $this->departmentsAndPeople();

        $this->actingAs($manager)
            ->get(route('exports.schedule', $this->query(['department_id' => $sales->id])))
            ->assertForbidden();

        $otherEmployee = $this->user($sales, employeeId: 'EMP-2002', firstName: 'Sara', lastName: 'Sales');

        $this->actingAs($manager)
            ->getJson(route('exports.schedule', $this->query(['user_id' => $otherEmployee->id])))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_admin_can_export_any_department(): void
    {
        [$ops, $sales, , , $salesEmployee, $shift] = $this->departmentsAndPeople();
        $admin = $this->user($ops, role: 'admin', employeeId: 'EMP-3003', firstName: 'Ava', lastName: 'Admin');
        $this->assignment($salesEmployee, $sales, $shift, '2026-10-20', '08:00', '16:00');

        $response = $this->actingAs($admin)->get(route('exports.schedule', $this->query(['department_id' => $sales->id])));
        $response->assertOk();

        $workbook = $this->responseWorkbook($response);
        $this->assertSame(7, $workbook->getSheetByName('Schedule')->getHighestRow());
        $this->assertSame('Sara Sales', $workbook->getSheetByName('Schedule')->getCell('D7')->getValue());
        $this->assertStringNotContainsString('Owen Park', $this->allText($workbook));
    }

    public function test_export_validation_errors_are_returned_as_json(): void
    {
        $admin = $this->user($this->department(), role: 'admin', employeeId: 'EMP-4001');

        $this->actingAs($admin)
            ->getJson(route('exports.schedule', $this->query(['format' => 'xls'])))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');

        $this->actingAs($admin)
            ->getJson(route('exports.schedule', $this->query(['from' => '2026-10-25', 'to' => '2026-10-19'])))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_csv_download_is_a_real_csv_with_filename_and_safe_contents(): void
    {
        [$ops, , , $opsEmployee, , $shift] = $this->departmentsAndPeople();
        $admin = $this->user($ops, role: 'admin', employeeId: 'EMP-6001', firstName: 'Ava', lastName: 'Admin');
        $this->assignment($opsEmployee, $ops, $shift, '2026-10-19', '08:00', '16:00');

        $response = $this->actingAs($admin)->get(route('exports.schedule', $this->query(['format' => 'csv'])));
        $response->assertOk();
        $response->assertDownload('Axcess_Schedule_2026-10-19_to_2026-10-25.csv');

        $contentType = $response->headers->get('content-type');
        $this->assertStringStartsWith('text/csv', (string) $contentType);

        $path = $response->baseResponse->getFile()->getPathname();
        $contents = file_get_contents($path);

        $this->assertStringStartsWith('"Schedule Date"', $contents);
        $this->assertStringContainsString('"Owen Park"', $contents);
        $this->assertStringNotContainsString('PK', substr($contents, 0, 2));
        $this->assertDatabaseHas('export_logs', [
            'user_id' => $admin->id,
            'type' => 'schedule',
            'format' => 'csv',
        ]);
    }

    public function test_export_logs_never_contain_sensitive_notes_or_credentials(): void
    {
        [$ops, , , , , $shift] = $this->departmentsAndPeople();
        $admin = $this->user($ops, role: 'admin', employeeId: 'EMP-6002', firstName: 'Ava', lastName: 'Admin');
        $employee = $this->user($ops, employeeId: 'EMP-6003', firstName: 'Nina', lastName: 'Notes');
        $this->assignment($employee, $ops, $shift, '2026-10-19', '08:00', '16:00', [
            'notes' => 'Confidential manager note',
            'replacement_reason' => 'Private medical reason',
        ]);

        $this->actingAs($admin)
            ->get(route('exports.schedule', $this->query(['include_sensitive' => 1])))
            ->assertOk();

        $log = ExportLog::latest('id')->firstOrFail();
        $serialized = json_encode($log->filters);

        $this->assertStringNotContainsString('Confidential manager note', (string) $serialized);
        $this->assertStringNotContainsString('Private medical reason', (string) $serialized);
        $this->assertStringNotContainsString('password', (string) $serialized);
    }

    /**
     * @return array{0: Department, 1: Department, 2: User, 3: User, 4: User, 5: ShiftType}
     */
    private function departmentsAndPeople(): array
    {
        $ops = $this->department('Operations', 'OPS');
        $sales = $this->department('Sales', 'SALES');
        $manager = $this->user($ops, role: 'manager', employeeId: 'EMP-2000', firstName: 'Mona', lastName: 'Manager');
        $opsEmployee = $this->user($ops, employeeId: 'EMP-1001', firstName: 'Owen', lastName: 'Park');
        $salesEmployee = $this->user($sales, employeeId: 'EMP-1002', firstName: 'Sara', lastName: 'Sales');

        return [$ops, $sales, $manager, $opsEmployee, $salesEmployee, $this->shiftType('Day Shift', '08:00:00', '16:00:00')];
    }

    /**
     * @return array<string, mixed>
     */
    private function query(array $overrides = []): array
    {
        return array_merge([
            'format' => 'xlsx',
            'mode' => 'custom',
            'from' => '2026-10-19',
            'to' => '2026-10-25',
            'worksheets' => ['schedule'],
        ], $overrides);
    }

    private function responseWorkbook($response): Spreadsheet
    {
        $path = $response->baseResponse->getFile()->getPathname();

        return IOFactory::createReader('Xlsx')->load($path);
    }

    private function allText(Spreadsheet $workbook): string
    {
        $values = [];

        foreach ($workbook->getAllSheets() as $sheet) {
            foreach ($sheet->toArray(null, true, false, false) as $row) {
                foreach ($row as $value) {
                    if (is_scalar($value)) {
                        $values[] = (string) $value;
                    }
                }
            }
        }

        return implode("\n", $values);
    }
}
