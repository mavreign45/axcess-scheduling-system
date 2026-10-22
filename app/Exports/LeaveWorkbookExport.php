<?php

namespace App\Exports;

use App\Exports\Sheets\GenericSheet;
use App\Exports\Support\ExportOptions;
use App\Services\ExportDataService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithProperties;

class LeaveWorkbookExport implements Export, WithMultipleSheets, WithPreCalculateFormulas, WithProperties
{
    public function __construct(
        private readonly ExportOptions $options,
        private readonly ExportDataService $data,
    ) {}

    public function sheets(): array
    {
        $leave = $this->data->leaveAbsences($this->options);
        $summary = $this->data->leaveSummary($this->options);

        return [
            $this->makeSheet('Leave & Absences', 'Leave and absence export', $leave),
            $this->makeSheet('Leave Summary', 'Leave totals by type and status', $summary),
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'Axcess',
            'title' => 'Axcess Leave Export',
            'company' => 'Axcess',
            'description' => 'Axcess leave and absence export',
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
