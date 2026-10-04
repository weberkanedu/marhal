<?php

namespace App\Reports;

use Illuminate\Support\Str;

/**
 * Biçimden bağımsız rapor: başlık, sütunlar, satırlar ve alt toplamlar.
 * Aynı nesne hem Excel'e (XlsxExport) hem PDF'e (resources/views/reports/table) dönüşür.
 * Faz 2–3'teki oda, otobüs, uçuş listeleri de bu yapıyla üretilecek.
 */
final readonly class Report
{
    /**
     * @param  list<Column>  $columns
     * @param  array<int, array<array-key, mixed>>  $rows
     * @param  list<string>  $subtitle  Başlık altı bilgi satırları (tur, tarih aralığı, filtre…)
     * @param  array<int, array<array-key, mixed>>  $totals  Alt toplam satırları (sütun anahtarı → değer)
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $columns,
        public array $rows,
        public array $subtitle = [],
        public array $totals = [],
        public bool $landscape = false,
    ) {}

    public function filename(string $extension): string
    {
        return Str::slug($this->title, '-', 'tr').'-'.now()->format('Y-m-d').'.'.$extension;
    }
}
