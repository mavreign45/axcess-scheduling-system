<?php

namespace App\Exports;

use App\Exports\Sheets\GenericSheet;
use App\Exports\Support\ExportOptions;
use App\Services\ExportDataService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithProperties;

class ReportsWorkbookExport implements Export, WithMultipleSheets, WithPreCalculateFormulas, WithProperties
{
    public function __construct(
        private readonly ExportOptions $options,
        private readonly ExportDataService $data,
    ) {}

    public function sheets(): array
    {
        $definitions = [
            'overview' => fn () => $this->makeSheet('Overview', 'Management report overview', $this->data->overview($this->options)),
            'schedule' => fn () => $this->makeSheet('Schedule', 'Shift schedule', $this->data->schedule($this->options)),
            'staff_summary' => fn () => $this->makeSheet('Staff Summary', 'Scheduled hours and staff summary', $this->data->staffSummary($this->options)),
            'coverage' => fn () => $this->makeSheet('Coverage', 'Staffing coverage and gaps', $this->data->coverage($this->options)),
            'breaks' => fn () => $this->makeSheet('Break Coverage', 'Break coverage exceptions', $this->data->breakCoverage($this->options)),
            'leave' => fn () => $this->makeSheet('Leave & Absences', 'Leave and absence totals', $this->data->leaveAbsences($this->options)),
            'leave_summary' => fn () => $this->makeSheet('Leave Summary', 'Leave totals by type and status', $this->data->leaveSummary($this->options)),
            'standby' => fn () => $this->makeSheet('Standby & Replacements', 'Standby duties and replacement history', $this->data->standbyReplacements($this->options)),
            'conflicts' => fn () => $this->makeSheet('Conflicts', 'Unresolved scheduling conflicts', $this->data->conflicts($this->options)),
            'unassigned' => fn () => $this->makeSheet('Unassigned Shifts', 'Shifts without an assigned employee', $this->data->unassigned($this->options)),
            'replacements' => fn () => $this->makeSheet('Replacement History', 'Shift replacement history', $this->data->replacementHistory($this->options)),
        ];

        $keys = match ($this->options->reportType) {
            'staff_summary' => ['overview', 'staff_summary', 'unassigned'],
            'coverage' => ['overview', 'coverage', 'unassigned'],
            'breaks' => ['overview', 'breaks'],
            'leave' => ['overview', 'leave', 'leave_summary'],
            'conflicts' => ['overview', 'conflicts', 'unassigned'],
            'replacements' => ['overview', 'standby', 'replacements'],
            default => ['overview', 'schedule', 'staff_summary', 'coverage', 'breaks', 'leave', 'leave_summary', 'standby', 'conflicts', 'unassigned', 'replacements'],
        };

        return array_map(fn (string $key) => $definitions[$key](), $keys);
    }

    public function properties(): array
    {
        return [
            'creator' => 'Axcess',
            'title' => 'Axcess Management Report',
            'company' => 'Axcess',
            'description' => 'Axcess scheduling management report generated '.now()->toDateTimeString(),
        ];
    }

    private function makeSheet(string $title, string $reportTitle, array $data): GenericSheet
    {
        return new GenericSheet(
            $title,
            $reportTitle,
            $this->options->periodLabel(),
            $this->options->filtersLabel(),
            $this->options->generatedLabel(),
            $data['headings'],
            $data['rows'],
            $data['formats'],
            $data['widths'],
            $data['wrap'],
        );
    }
}
