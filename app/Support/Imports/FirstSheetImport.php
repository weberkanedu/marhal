<?php

namespace App\Support\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Yüklenen dosyanın yalnızca ilk sayfasını ham satırlar olarak okur (biçimlendirme yok:
 * tarihler Excel sayısı, numaralar sayı olarak gelir; PersonRowNormalizer çevirir).
 */
class FirstSheetImport implements ToArray, WithMultipleSheets
{
    /** @var array<int, array<int, mixed>> */
    public array $rows = [];

    /**
     * @param  array<int, array<int, mixed>>  $array
     */
    public function array(array $array): void
    {
        $this->rows = $array;
    }

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }
}
