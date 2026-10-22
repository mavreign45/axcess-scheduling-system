<?php

namespace App\Exports\Support;

use DateTimeInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseSheet implements FromArray, WithEvents, WithTitle
{
    use SanitizesSpreadsheetData;

    protected string $title;

    protected string $reportTitle;

    protected string $periodLabel;

    protected string $filtersLabel;

    protected string $generatedLabel;

    /** @var array<int, string> */
    protected array $headings;

    /** @var array<int, array<int, mixed>> */
    protected array $rows;

    /** @var array<string, string> column letter => number format */
    protected array $formats = [];

    /** @var array<string, float> column letter => width */
    protected array $widths = [];

    /** @var array<int, string> zero-based column indexes that should wrap */
    protected array $wrapColumns = [];

    protected bool $showAutoFilter = true;

    protected string $tabColor = '0F766E';

    public function __construct(
        string $title,
        string $reportTitle,
        string $periodLabel,
        string $filtersLabel,
        string $generatedLabel,
        array $headings,
        array $rows,
        array $formats = [],
        array $widths = [],
        array $wrapColumns = [],
    ) {
        $this->title = $title;
        $this->reportTitle = $reportTitle;
        $this->periodLabel = $periodLabel;
        $this->filtersLabel = $filtersLabel;
        $this->generatedLabel = $generatedLabel;
        $this->headings = array_values($headings);
        $this->rows = array_values($rows);
        $this->formats = $formats;
        $this->widths = $widths;
        $this->wrapColumns = $wrapColumns;
    }

    public function array(): array
    {
        $data = [
            ['Axcess — Staff Scheduling & Workforce Management'],
            [$this->reportTitle],
            ['Reporting period: '.$this->periodLabel],
            [$this->generatedLabel],
            ['Filters: '.$this->filtersLabel],
            [],
            $this->headings,
        ];

        foreach ($this->rows as $row) {
            $data[] = $this->prepareRow($row);
        }

        return $data;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $this->formatSheet($event->sheet->getDelegate());
            },
        ];
    }

    protected function prepareRow(array $row): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value instanceof Formula) {
                return $value->value;
            }

            if ($value instanceof DateTimeInterface) {
                return Date::PHPToExcel($value);
            }

            return $this->sanitizeCell($value);
        }, array_values($row));
    }

    protected function formatSheet(Worksheet $sheet): void
    {
        $lastColumnIndex = max(1, count($this->headings));
        $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);
        $headerRow = 6;
        $firstDataRow = 7;
        $lastRow = $headerRow + count($this->rows);

        $sheet->setTitle(mb_substr($this->title, 0, 31));
        $sheet->setShowGridLines(false);
        $sheet->getSheetView()->setZoomScale(90);
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(21);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F172A']],
        ]);

        $sheet->getStyle("A3:{$lastColumn}5")->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '475569']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0B4F4A']],
            ],
        ]);

        if ($this->rows !== []) {
            $sheet->getStyle("A{$firstDataRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1E293B']],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Light banding improves readability while keeping filters usable.
            for ($row = $firstDataRow; $row <= $lastRow; $row++) {
                if ($row % 2 === 1) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }
            }
        }

        if ($this->showAutoFilter) {
            $filterLastRow = $this->rows === [] ? $headerRow : $lastRow;
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$filterLastRow}");
        }

        foreach ($this->formats as $column => $format) {
            if ($this->rows !== []) {
                $sheet->getStyle("{$column}{$firstDataRow}:{$column}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode($format);
            }
        }

        foreach ($this->headings as $index => $heading) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $width = $this->widths[$column] ?? $this->inferWidth($heading);
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        foreach ($this->wrapColumns as $index) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getStyle("{$column}{$firstDataRow}:{$column}{$lastRow}")
                ->getAlignment()
                ->setWrapText(true);
        }

        if ($this->rows !== []) {
            $sheet->getRowDimension($firstDataRow)->setRowHeight(-1);
        }

        $sheet->freezePane('A'.$firstDataRow);
        $sheet->getTabColor()->setRGB($this->tabColor);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastRow}");
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getHeaderFooter()
            ->setOddFooter('&LAxcess&CConfidential&RPage &P of &N');

        if ($this->rows === []) {
            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getAlignment()->setWrapText(true);
        }
    }

    protected function inferWidth(string $heading): float
    {
        return min(32, max(12, strlen($heading) + 4));
    }
}
