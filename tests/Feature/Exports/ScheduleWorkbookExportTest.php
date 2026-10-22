<?php

namespace Tests\Feature\Exports;

use App\Exports\ScheduleWorkbookExport;
use App\Exports\StaffWorkbookExport;
use App\Services\ExportDataService;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ScheduleWorkbookExportTest extends ExportTestCase
{
    public function test_overnight_shift_keeps_both_breaks_and_correct_duration(): void
    {
        $department = $this->department();
        $employee = $this->user($department, firstName: 'Sofia', lastName: 'Ramirez', employeeId: 'EMP-2001');
        $shift = $this->shiftType('Extended Night');
        $assignment = $this->assignment($employee, $department, $shift, '2026-10-19', '18:00', '08:00');

        $this->addBreak($assignment, 'Meal break', '2026-10-19 22:00', '2026-10-19 22:30');
        $this->addBreak($assignment, 'Rest break', '2026-10-20 02:00', '2026-10-20 02:30');

        $options = $this->exportOptions([
            'worksheets' => ['schedule', 'breaks'],
            'generatedBy' => $employee,
        ]);
        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));

        $row = $this->rowFor($workbook, 'Schedule', 4, 'Sofia Ramirez');
        $this->assertNotNull($row, 'The overnight assignment was not exported.');

        $schedule = $workbook->getSheetByName('Schedule');
        $this->assertSame('Yes', $schedule->getCell('T'.$row)->getValue());
        $this->assertSame(2, (int) $schedule->getCell('L'.$row)->getValue());
        $this->assertStringContainsString('22:00–22:30 Meal break', (string) $schedule->getCell('M'.$row)->getValue());
        $this->assertStringContainsString('02:00–02:30 Rest break', (string) $schedule->getCell('M'.$row)->getValue());
        $this->assertSame(14.0, (float) $schedule->getCell('N'.$row)->getValue());
        $this->assertSame(1.0, (float) $schedule->getCell('O'.$row)->getValue());
        $this->assertSame(13.0, (float) $schedule->getCell('P'.$row)->getValue());
        $this->assertSame('20-Oct-2026 08:00', $schedule->getCell('I'.$row)->getFormattedValue());
        $this->assertSame('19-Oct-2026 22:00', $schedule->getCell('J'.$row)->getFormattedValue());

        $breaks = $workbook->getSheetByName('Breaks');
        $exported = [];

        for ($breakRow = 7; $breakRow <= $breaks->getHighestRow(); $breakRow++) {
            if ($breaks->getCell('D'.$breakRow)->getValue() === 'Sofia Ramirez') {
                $exported[] = [
                    (int) $breaks->getCell('G'.$breakRow)->getValue(),
                    $breaks->getCell('H'.$breakRow)->getValue(),
                    $breaks->getCell('I'.$breakRow)->getFormattedValue(),
                    $breaks->getCell('J'.$breakRow)->getFormattedValue(),
                ];
            }
        }

        $this->assertCount(2, $exported, 'Both breaks must be present in the Breaks worksheet.');
        $this->assertSame([1, 'Meal break', '19-Oct-2026 22:00', '19-Oct-2026 22:30'], $exported[0]);
        $this->assertSame([2, 'Rest break', '20-Oct-2026 02:00', '20-Oct-2026 02:30'], $exported[1]);
    }

    public function test_empty_period_still_produces_a_valid_branded_workbook(): void
    {
        $start = Carbon::parse('2030-01-01')->startOfDay();
        $end = Carbon::parse('2030-01-02')->endOfDay();
        $admin = $this->user($this->department(), role: 'admin', employeeId: 'EMP-3001', firstName: 'Avery', lastName: 'Quinn');

        $options = $this->exportOptions([
            'start' => $start,
            'end' => $end,
            'worksheets' => ['schedule', 'staff_summary', 'breaks', 'leave_absences', 'standby_replacements', 'conflicts'],
            'generatedBy' => $admin,
        ]);

        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));

        $this->assertSame(
            ['Schedule', 'Staff Summary', 'Breaks', 'Leave & Absences', 'Standby & Replacements', 'Conflicts'],
            $workbook->getSheetNames(),
        );

        $schedule = $workbook->getSheetByName('Schedule');
        $this->assertSame(6, $schedule->getHighestRow(), 'An empty schedule should contain only the title, metadata and header rows.');
        $this->assertSame('Schedule Date', $schedule->getCell('A6')->getValue());
        $this->assertSame('A6:T6', $schedule->getAutoFilter()->getRange());
        $this->assertSame('A7', $schedule->getFreezePane());
        $this->assertSame('Axcess — Staff Scheduling & Workforce Management', $schedule->getCell('A1')->getValue());
        $this->assertStringContainsString('Jan 1, 2030', (string) $schedule->getCell('A3')->getValue());
    }

    public function test_workbook_branding_formats_filters_and_filename(): void
    {
        $department = $this->department();
        $admin = $this->user($department, role: 'admin', employeeId: 'EMP-3002', firstName: 'Avery', lastName: 'Quinn');
        $employee = $this->user($department, employeeId: 'EMP-3003', firstName: 'Jamie', lastName: 'Lee');
        $shift = $this->shiftType('Day Shift', '08:00:00', '16:00:00');
        $this->assignment($employee, $department, $shift, '2026-10-19', '08:00', '16:00');

        $options = $this->exportOptions([
            'worksheets' => ['schedule', 'staff_summary'],
            'generatedBy' => $admin,
        ]);

        $this->assertSame('Axcess_Schedule_2026-10-19_to_2026-10-25.xlsx', $options->filename('schedule'));

        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));

        $this->assertSame('Axcess Schedule Export', $workbook->getProperties()->getTitle());
        $this->assertSame('Axcess', $workbook->getProperties()->getCreator());
        $this->assertSame('Axcess', $workbook->getProperties()->getCompany());
        $this->assertSame(['Schedule', 'Staff Summary'], $workbook->getSheetNames());

        $schedule = $workbook->getSheetByName('Schedule');
        $this->assertSame('Axcess — Staff Scheduling & Workforce Management', $schedule->getCell('A1')->getValue());
        $this->assertSame('Shift schedule export', $schedule->getCell('A2')->getValue());
        $this->assertStringContainsString('Reporting period: Oct 19, 2026 – Oct 25, 2026', (string) $schedule->getCell('A3')->getValue());
        $this->assertStringContainsString('Generated by: Avery Quinn (Administrator)', (string) $schedule->getCell('A4')->getValue());
        $this->assertStringContainsString('Generated:', (string) $schedule->getCell('A4')->getValue());

        $this->assertTrue($schedule->getStyle('A6')->getFont()->getBold());
        $this->assertSame('FFFFFFFF', $schedule->getStyle('A6')->getFont()->getColor()->getARGB());
        $this->assertSame('FF0F766E', $schedule->getStyle('A6')->getFill()->getStartColor()->getARGB());
        $this->assertSame('yyyy-mm-dd', $schedule->getStyle('A7')->getNumberFormat()->getFormatCode());
        $this->assertSame('dd-mmm-yyyy hh:mm', $schedule->getStyle('H7')->getNumberFormat()->getFormatCode());
        $this->assertSame('A7', $schedule->getFreezePane());
        $this->assertSame('A6:T7', $schedule->getAutoFilter()->getRange());
        $this->assertGreaterThan(10, $schedule->getColumnDimension('D')->getWidth());
        $this->assertArrayHasKey('A1:T1', $schedule->getMergeCells());
        $this->assertSame('&LAxcess&CConfidential&RPage &P of &N', $schedule->getHeaderFooter()->getOddFooter());
    }

    public function test_schedule_filters_limit_rows_by_period_department_and_employee(): void
    {
        $ops = $this->department('Operations', 'OPS');
        $support = $this->department('Customer Support', 'CS');

        $opsEmployee = $this->user($ops, employeeId: 'EMP-4001', firstName: 'Owen', lastName: 'Park');
        $supportEmployee = $this->user($support, employeeId: 'EMP-4002', firstName: 'Sam', lastName: 'Rivera');
        $shift = $this->shiftType('Day Shift', '08:00:00', '16:00:00');

        $this->assignment($opsEmployee, $ops, $shift, '2026-10-19', '08:00', '16:00');
        $this->assignment($supportEmployee, $support, $shift, '2026-10-20', '08:00', '16:00');
        $this->assignment($opsEmployee, $ops, $shift, '2026-11-02', '08:00', '16:00');

        $byDepartment = $this->exportOptions([
            'departmentId' => $ops->id,
            'worksheets' => ['schedule'],
        ]);
        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($byDepartment, app(ExportDataService::class)), ExcelWriter::XLSX));
        $this->assertSame(7, $workbook->getSheetByName('Schedule')->getHighestRow());
        $this->assertSame('Owen Park', $workbook->getSheetByName('Schedule')->getCell('D7')->getValue());

        $byEmployee = $this->exportOptions([
            'userId' => $supportEmployee->id,
            'worksheets' => ['schedule'],
        ]);
        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($byEmployee, app(ExportDataService::class)), ExcelWriter::XLSX));
        $this->assertSame(7, $workbook->getSheetByName('Schedule')->getHighestRow());
        $this->assertSame('Sam Rivera', $workbook->getSheetByName('Schedule')->getCell('D7')->getValue());

        $outside = $this->exportOptions([
            'start' => Carbon::parse('2026-11-02')->startOfDay(),
            'end' => Carbon::parse('2026-11-02')->endOfDay(),
            'departmentId' => $support->id,
            'worksheets' => ['schedule'],
        ]);
        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($outside, app(ExportDataService::class)), ExcelWriter::XLSX));
        $this->assertSame(6, $workbook->getSheetByName('Schedule')->getHighestRow());
    }

    public function test_sensitive_schedule_columns_are_only_exported_when_requested(): void
    {
        $department = $this->department();
        $admin = $this->user($department, role: 'admin', employeeId: 'EMP-5000', firstName: 'Avery', lastName: 'Quinn');
        $employee = $this->user($department, employeeId: 'EMP-5001', firstName: 'Dana', lastName: 'Cole');
        $replacement = $this->user($department, employeeId: 'EMP-5002', firstName: 'Riley', lastName: 'West');
        $shift = $this->shiftType('Day Shift', '08:00:00', '16:00:00');

        $assignment = $this->assignment($employee, $department, $shift, '2026-10-19', '08:00', '16:00', [
            'replacement_user_id' => $replacement->id,
            'replacement_reason' => 'Emergency cover for a family matter',
            'notes' => 'Confidential manager note — do not distribute.',
        ]);

        $standard = $this->exportOptions(['worksheets' => ['schedule'], 'generatedBy' => $admin]);
        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($standard, app(ExportDataService::class)), ExcelWriter::XLSX));
        $this->assertNotContains('Manager Notes', $this->headings($workbook, 'Schedule'));
        $this->assertNotContains('Replacement Reason', $this->headings($workbook, 'Schedule'));
        $this->assertStringNotContainsString('Confidential manager note', $this->allText($workbook));

        $sensitive = $this->exportOptions(['worksheets' => ['schedule'], 'generatedBy' => $admin, 'includeSensitive' => true]);
        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($sensitive, app(ExportDataService::class)), ExcelWriter::XLSX));
        $headings = $this->headings($workbook, 'Schedule');
        $this->assertContains('Manager Notes', $headings);
        $this->assertContains('Replacement Reason', $headings);
        $this->assertSame('Confidential manager note — do not distribute.', $workbook->getSheetByName('Schedule')->getCell('V7')->getValue());
        $this->assertSame('Emergency cover for a family matter', $workbook->getSheetByName('Schedule')->getCell('U7')->getValue());
        $this->assertSame($assignment->id, $assignment->fresh()->id);
    }

    public function test_text_values_are_sanitized_and_employee_secrets_are_never_exported(): void
    {
        $department = $this->department();
        $employee = $this->user($department, employeeId: 'EMP-6001', firstName: '=HYPERLINK("http://example.test","click")', lastName: 'Injected');
        $employee->password = 'known-plain-text-secret';
        $employee->save();

        $workbook = $this->workbookFrom(Excel::raw(new StaffWorkbookExport($this->exportOptions(['generatedBy' => $employee]), app(ExportDataService::class)), ExcelWriter::XLSX));

        $staff = $workbook->getSheetByName('Staff');
        $this->assertSame('\'=HYPERLINK("http://example.test","click") Injected', $staff->getCell('B7')->getValue());
        $this->assertStringNotContainsString('known-plain-text-secret', $this->allText($workbook));
        $this->assertStringNotContainsString('$2y$', $this->allText($workbook));

        foreach ($this->headings($workbook, 'Staff') as $heading) {
            $this->assertStringNotContainsStringIgnoringCase('password', $heading);
            $this->assertStringNotContainsStringIgnoringCase('token', $heading);
        }
    }

    public function test_selected_worksheets_and_their_order_are_respected(): void
    {
        $admin = $this->user($this->department(), role: 'admin', employeeId: 'EMP-7001');
        $options = $this->exportOptions([
            'worksheets' => ['conflicts', 'breaks'],
            'generatedBy' => $admin,
        ]);

        $workbook = $this->workbookFrom(Excel::raw(new ScheduleWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));

        $this->assertSame(['Conflicts', 'Breaks'], $workbook->getSheetNames());
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
