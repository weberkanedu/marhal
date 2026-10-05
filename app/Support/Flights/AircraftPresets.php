<?php

namespace App\Support\Flights;

/**
 * Hazır uçak tipleri (ekonomi kabini, yaygın düzen). Acente tek tıkla kendi listesine ekler,
 * sonra kendi havayolunun düzenine göre değiştirebilir (sıra aralığı, acil çıkışlar).
 */
final class AircraftPresets
{
    /**
     * @return list<array{key: string, name: string, cabin: string, first_row: int, last_row: int, exit_rows: list<int>}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'a321neo', 'name' => 'Airbus A321neo', 'cabin' => '3-3', 'first_row' => 1, 'last_row' => 38, 'exit_rows' => [11, 12, 25]],
            ['key' => 'b737-800', 'name' => 'Boeing 737-800', 'cabin' => '3-3', 'first_row' => 1, 'last_row' => 32, 'exit_rows' => [15, 16]],
            ['key' => 'a330-300', 'name' => 'Airbus A330-300', 'cabin' => '2-4-2', 'first_row' => 10, 'last_row' => 45, 'exit_rows' => [10, 25]],
            ['key' => 'b777-300er', 'name' => 'Boeing 777-300ER', 'cabin' => '3-4-3', 'first_row' => 20, 'last_row' => 52, 'exit_rows' => [20, 35]],
            ['key' => 'b787-9', 'name' => 'Boeing 787-9', 'cabin' => '3-3-3', 'first_row' => 18, 'last_row' => 45, 'exit_rows' => [18, 31]],
        ];
    }

    /**
     * @return array{key: string, name: string, cabin: string, first_row: int, last_row: int, exit_rows: list<int>}|null
     */
    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }
}
