<?php

namespace App\Exports;

use App\Exports\Sheets\GenericSheet;
use App\Exports\Support\ExportOptions;
use App\Services\ExportDataService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithProperties;

class StaffWorkbookExport implements Export, WithMultipleSheets, WithPreCalculateFormulas, WithProperties
{
    public function __construct(
        private readonly ExportOptions $options,
        private readonly ExportDataService $data,
    ) {}

    public function sheets(): array
    {
        $data = $this->data->staff($this->options);

        return [
            new GenericSheet(
                'Staff',
                'Staff directory export',
                $this->options->periodLabel(),
                $this->options->filtersLabel(),
                $this->options->generatedLabel(),
                $data['headings'],
                $data['rows'],
                $data['formats'],
                $data['widths'],
                $data['wrap'],
            ),
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'Axcess',
            'title' => 'Axcess Staff Export',
            'company' => 'Axcess',
            'description' => 'Axcess staff directory export',
        ];
    }
}
