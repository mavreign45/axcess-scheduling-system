<?php

namespace Tests\Feature\Exports;

use App\Exports\LeaveWorkbookExport;
use App\Exports\ReportsWorkbookExport;
use App\Exports\StaffWorkbookExport;
use App\Models\Absence;
use App\Models\LeaveRequest;
use App\Services\ExportDataService;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class StaffLeaveReportExportTest extends ExportTestCase
{
    public function test_staff_export_contains_directory_fields_and_only_includes_contacts_when_requested(): void
    {
        $department = $this->department();
        $user = $this->user(
            $department,
            employeeId: 'EMP-8001',
            firstName: 'Taylor',
            lastName: 'Brooks',
            attributes: [
                'email' => 'taylor.brooks@axcess.test',
                'phone' => '+1 555 0100',
                'hire_date' => '2024-04-01',
                'max_weekly_hours' => 37.5,
                'standby_eligible' => true,
            ],
        );

        $standard = $this->exportOptions(['generatedBy' => $user]);
        $workbook = $this->workbookFrom(Excel::raw(new StaffWorkbookExport($standard, app(ExportDataService::class)), ExcelWriter::XLSX));
        $headings = $this->headings($workbook, 'Staff');

        $this->assertSame(
            ['Employee ID', 'Full Name', 'Department', 'Job Position', 'Role', 'Employment Status', 'Employment Type', 'Hire Date', 'Standby Eligible', 'Maximum Weekly Hours', 'Account Status'],
            $headings,
        );
        $this->assertNotContains('Email', $headings);
        $this->assertNotContains('Phone', $headings);
        $this->assertStringNotContainsString('taylor.brooks@axcess.test', $this->allText($workbook));
        $this->assertStringNotContainsString('+1 555 0100', $this->allText($workbook));
        $this->assertSame('Taylor Brooks', $workbook->getSheetByName('Staff')->getCell('B7')->getValue());
        $this->assertSame('Yes', $workbook->getSheetByName('Staff')->getCell('I7')->getValue());
        $this->assertSame('37.5', (string) $workbook->getSheetByName('Staff')->getCell('J7')->getValue());

        $withContacts = $this->exportOptions(['generatedBy' => $user, 'includeContacts' => true]);
        $workbook = $this->workbookFrom(Excel::raw(new StaffWorkbookExport($withContacts, app(ExportDataService::class)), ExcelWriter::XLSX));
        $headings = $this->headings($workbook, 'Staff');

        $this->assertContains('Email', $headings);
        $this->assertContains('Phone', $headings);
        $this->assertSame('taylor.brooks@axcess.test', $workbook->getSheetByName('Staff')->getCell('H7')->getValue());
        $this->assertSame('\'+1 555 0100', $workbook->getSheetByName('Staff')->getCell('I7')->getValue());
        $this->assertSame('2024-04-01', $workbook->getSheetByName('Staff')->getCell('J7')->getFormattedValue());
    }

    public function test_staff_export_respects_employment_and_standby_filters(): void
    {
        $department = $this->department();
        $this->user($department, employeeId: 'EMP-8101', firstName: 'Active', lastName: 'Standby', attributes: ['standby_eligible' => true, 'employment_status' => 'active']);
        $this->user($department, employeeId: 'EMP-8102', firstName: 'Active', lastName: 'Regular', attributes: ['standby_eligible' => false, 'employment_status' => 'active']);
        $this->user($department, employeeId: 'EMP-8103', firstName: 'Inactive', lastName: 'Person', attributes: ['employment_status' => 'inactive']);

        $options = $this->exportOptions(['standbyOnly' => true, 'employmentStatus' => 'active']);
        $workbook = $this->workbookFrom(Excel::raw(new StaffWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));
        $staff = $workbook->getSheetByName('Staff');

        $this->assertSame(7, $staff->getHighestRow());
        $this->assertSame('Active Standby', $staff->getCell('B7')->getValue());
    }

    public function test_leave_export_combines_leave_and_absences_and_keeps_sensitive_fields_conditional(): void
    {
        $department = $this->department();
        $approver = $this->user($department, role: 'manager', employeeId: 'EMP-9000', firstName: 'Morgan', lastName: 'Reed');
        $leaveEmployee = $this->user($department, employeeId: 'EMP-9001', firstName: 'Casey', lastName: 'Hughes');
        $absentEmployee = $this->user($department, employeeId: 'EMP-9002', firstName: 'Jordan', lastName: 'Blake');

        LeaveRequest::factory()->create([
            'user_id' => $leaveEmployee->id,
            'leave_type' => 'annual',
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-20',
            'duration_days' => 2,
            'status' => 'approved',
            'approver_id' => $approver->id,
            'approved_at' => '2026-10-10 09:00:00',
            'reason' => 'Family holiday',
            'supporting_information' => 'Booking reference AX-123',
        ]);

        Absence::factory()->create([
            'user_id' => $absentEmployee->id,
            'absence_date' => '2026-10-21',
            'type' => 'sick',
            'notes' => 'Medical certificate provided',
            'recorded_by' => $approver->id,
        ]);

        $start = Carbon::parse('2026-10-01')->startOfDay();
        $end = Carbon::parse('2026-10-31')->endOfDay();

        $standard = $this->exportOptions(['start' => $start, 'end' => $end, 'generatedBy' => $approver]);
        $workbook = $this->workbookFrom(Excel::raw(new LeaveWorkbookExport($standard, app(ExportDataService::class)), ExcelWriter::XLSX));
        $this->assertSame(['Leave & Absences', 'Leave Summary'], $workbook->getSheetNames());

        $headings = $this->headings($workbook, 'Leave & Absences');
        $this->assertCount(12, $headings);
        $this->assertNotContains('Reason', $headings);
        $this->assertStringNotContainsString('Family holiday', $this->allText($workbook));
        $this->assertStringNotContainsString('Medical certificate provided', $this->allText($workbook));

        $leaveSheet = $workbook->getSheetByName('Leave & Absences');
        $this->assertSame(8, $leaveSheet->getHighestRow());
        $this->assertSame('Leave', $leaveSheet->getCell('A7')->getValue());
        $this->assertSame('Absence', $leaveSheet->getCell('A8')->getValue());
        $this->assertSame('Approved', $leaveSheet->getCell('I7')->getValue());
        $this->assertSame('Morgan Reed', $leaveSheet->getCell('J7')->getValue());

        $summary = $workbook->getSheetByName('Leave Summary');
        $this->assertSame('TOTAL', $summary->getCell('A8')->getValue());
        $this->assertSame('=SUM(C7:C7)', $summary->getCell('C8')->getValue());
        $this->assertSame('=SUM(D7:D7)', $summary->getCell('D8')->getValue());
        $this->assertEquals(1, $summary->getCell('C8')->getCalculatedValue());
        $this->assertEquals(2.0, $summary->getCell('D8')->getCalculatedValue());

        $sensitive = $this->exportOptions(['start' => $start, 'end' => $end, 'generatedBy' => $approver, 'includeSensitive' => true]);
        $workbook = $this->workbookFrom(Excel::raw(new LeaveWorkbookExport($sensitive, app(ExportDataService::class)), ExcelWriter::XLSX));
        $headings = $this->headings($workbook, 'Leave & Absences');
        $this->assertCount(15, $headings);
        $this->assertContains('Reason', $headings);
        $this->assertContains('Supporting Information', $headings);
        $this->assertContains('Notes', $headings);
        $leaveSheet = $workbook->getSheetByName('Leave & Absences');
        $this->assertSame('Family holiday', $leaveSheet->getCell('M7')->getValue());
        $this->assertSame('Booking reference AX-123', $leaveSheet->getCell('N7')->getValue());
        $this->assertSame('Medical certificate provided', $leaveSheet->getCell('O8')->getValue());
    }

    public function test_empty_leave_export_is_valid_and_has_no_total_formula(): void
    {
        $admin = $this->user($this->department(), role: 'admin', employeeId: 'EMP-9100');
        $options = $this->exportOptions([
            'start' => Carbon::parse('2030-02-01')->startOfDay(),
            'end' => Carbon::parse('2030-02-28')->endOfDay(),
            'generatedBy' => $admin,
        ]);

        $workbook = $this->workbookFrom(Excel::raw(new LeaveWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));

        $this->assertSame(['Leave & Absences', 'Leave Summary'], $workbook->getSheetNames());
        $this->assertSame(6, $workbook->getSheetByName('Leave & Absences')->getHighestRow());
        $this->assertSame(6, $workbook->getSheetByName('Leave Summary')->getHighestRow());
        $this->assertSame('A6:L6', $workbook->getSheetByName('Leave & Absences')->getAutoFilter()->getRange());
        $this->assertSame('A6:D6', $workbook->getSheetByName('Leave Summary')->getAutoFilter()->getRange());
    }

    public function test_report_workbook_includes_requested_report_sheets_and_staff_summary_formulas(): void
    {
        $department = $this->department();
        $admin = $this->user($department, role: 'admin', employeeId: 'EMP-9200', firstName: 'Avery', lastName: 'Quinn');
        $employee = $this->user($department, employeeId: 'EMP-9201', firstName: 'Robin', lastName: 'Patel');
        $shift = $this->shiftType('Day Shift', '08:00:00', '16:00:00');

        $this->assignment($employee, $department, $shift, '2026-10-19', '08:00', '16:00', ['status' => 'completed']);
        $this->assignment($employee, $department, $shift, '2026-10-20', '08:00', '16:00', ['status' => 'completed']);

        $options = $this->exportOptions([
            'reportType' => 'staff_summary',
            'generatedBy' => $admin,
        ]);
        $workbook = $this->workbookFrom(Excel::raw(new ReportsWorkbookExport($options, app(ExportDataService::class)), ExcelWriter::XLSX));

        $this->assertSame(['Overview', 'Staff Summary', 'Unassigned Shifts'], $workbook->getSheetNames());
        $summary = $workbook->getSheetByName('Staff Summary');
        $this->assertSame('Robin Patel', $summary->getCell('B7')->getValue());
        $this->assertSame(2, (int) $summary->getCell('F7')->getValue());
        $this->assertSame(16.0, (float) $summary->getCell('G7')->getValue());
        $this->assertSame('=SUM(F7:F7)', $summary->getCell('F8')->getValue());
        $this->assertEquals(2, $summary->getCell('F8')->getCalculatedValue());
        $this->assertSame('TOTAL', $summary->getCell('A8')->getValue());

        $full = $this->exportOptions(['generatedBy' => $admin]);
        $workbook = $this->workbookFrom(Excel::raw(new ReportsWorkbookExport($full, app(ExportDataService::class)), ExcelWriter::XLSX));
        $this->assertSame([
            'Overview', 'Schedule', 'Staff Summary', 'Coverage', 'Break Coverage', 'Leave & Absences',
            'Leave Summary', 'Standby & Replacements', 'Conflicts', 'Unassigned Shifts', 'Replacement History',
        ], $workbook->getSheetNames());
        $this->assertSame('Axcess Management Report', $workbook->getProperties()->getTitle());
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
