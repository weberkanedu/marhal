<?php

namespace App\Reports\Exporters;

use App\Reports\Column;
use App\Reports\Report;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Report → .xlsx. Üstte acente ve rapor başlığı, ardından tablo ve toplam satırları.
 * Para ve tarih hücreleri metin değil gerçek sayı/tarih olarak yazılır (Excel'de toplanabilir).
 */
class XlsxExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithHeadings, WithStyles, WithTitle
{
    private const HEADER_ROWS = 3;

    /**
     * @param  list<string>  $header  Acente adı vb. üst bilgi
     */
    public function __construct(
        private readonly Report $report,
        private readonly array $header = [],
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = array_map(fn (array $row) => $this->line($row), $this->report->rows);

        foreach ($this->report->totals as $total) {
            $rows[] = $this->line($total);
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_map(fn (Column $c) => $c->label, $this->report->columns);
    }

    public function startCell(): string
    {
        return 'A'.(self::HEADER_ROWS + 1);
    }

    public function title(): string
    {
        return mb_substr($this->report->title, 0, 31);
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->report->columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $formats[$letter] = match ($column->type) {
                'money' => '#,##0.00',
                'date' => 'dd.mm.yyyy',
                'number' => NumberFormat::FORMAT_NUMBER,
                default => NumberFormat::FORMAT_TEXT,
            };
        }

        return $formats;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($this->report->columns)));
        $headingRow = self::HEADER_ROWS + 1;

        $sheet->setCellValue('A1', $this->report->title);
        $sheet->setCellValue('A2', implode('  ·  ', [...$this->header, ...$this->report->subtitle]));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->freezePane('A'.($headingRow + 1));

        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['color' => ['rgb' => '666666']]],
            $headingRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']],
            ],
        ];

        // Toplam satırları kalın
        $firstTotal = $headingRow + count($this->report->rows) + 1;
        foreach (array_keys($this->report->totals) as $offset) {
            $styles[$firstTotal + $offset] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    private function line(array $row): array
    {
        return array_map(function (Column $column) use ($row) {
            $value = $row[$column->key] ?? null;

            if ($value === null || $value === '') {
                return null;
            }

            return match ($column->type) {
                'money', 'number' => is_numeric($value) ? (float) $value : $value,
                'date' => ExcelDate::PHPToExcel(CarbonImmutable::parse((string) $value)),
                default => (string) $value,
            };
        }, $this->report->columns);
    }
}
