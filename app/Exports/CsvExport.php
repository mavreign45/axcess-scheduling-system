<?php

namespace App\Exports;

use App\Exports\Support\Formula;
use App\Exports\Support\SanitizesSpreadsheetData;
use DateTimeInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithProperties;

class CsvExport implements FromArray, WithProperties
{
    use SanitizesSpreadsheetData;

    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly string $title = 'Axcess export',
    ) {}

    public function array(): array
    {
        $data = [$this->headings];

        foreach ($this->rows as $row) {
            $data[] = array_map(function (mixed $value): mixed {
                if ($value instanceof Formula) {
                    return $value->value;
                }

                if ($value instanceof DateTimeInterface) {
                    return $value->format('Y-m-d H:i:s');
                }

                return $this->sanitizeCell($value);
            }, array_values($row));
        }

        return $data;
    }

    public function properties(): array
    {
        return [
            'creator' => 'Axcess',
            'title' => $this->title,
            'company' => 'Axcess',
        ];
    }
}
