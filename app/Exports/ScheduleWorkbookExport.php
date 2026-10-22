<?php

namespace App\Exports;

use App\Exports\Sheets\GenericSheet;
use App\Exports\Support\ExportOptions;
use App\Services\ExportDataService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithProperties;

class ScheduleWorkbookExport implements Export, WithMultipleSheets, WithPreCalculateFormulas, WithProperties
{
    public function __construct(
        private readonly ExportOptions $options,
        private readonly ExportDataService $data,
    ) {}

    public function sheets(): array
    {
        $sheetBuilders = [
            'schedule' => fn () => $this->makeSheet('Schedule', 'Shift schedule export', $this->data->schedule($this->options)),
            'staff_summary' => fn () => $this->makeSheet('Staff Summary', 'Scheduled hours and staff summary', $this->data->staffSummary($this->options)),
            'breaks' => fn () => $this->makeSheet('Breaks', 'Break periods linked to scheduled shifts', $this->data->breaks($this->options)),
            'leave_absences' => fn () => $this->makeSheet('Leave & Absences', 'Leave requests and recorded absences', $this->data->leaveAbsences($this->options)),
            'standby_replacements' => fn () => $this->makeSheet('Standby & Replacements', 'Standby duties and replacement history', $this->data->standbyReplacements($this->options)),
            'conflicts' => fn () => $this->makeSheet('Conflicts', 'Unresolved scheduling conflicts', $this->data->conflicts($this->options)),
        ];

        $selected = $this->options->worksheets ?: ['schedule'];
        $sheets = [];

        foreach ($selected as $key) {
            if (isset($sheetBuilders[$key])) {
                $sheets[] = $sheetBuilders[$key]();
            }
        }

        return $sheets ?: [$sheetBuilders['schedule']()];
    }

    public function properties(): array
    {
        return [
            'creator' => 'Axcess',
            'title' => 'Axcess Schedule Export',
            'company' => 'Axcess',
            'description' => 'Axcess staff scheduling export generated '.now()->toDateTimeString(),
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
