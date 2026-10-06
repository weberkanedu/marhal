<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Yaka kartı boyları (hepsi A4'e dizilir, kesik çizgiden kesilir).
 */
enum BadgeSize: string
{
    use HasOptions;

    case Portrait = 'dikey';
    case Landscape = 'yatay';
    case Card = 'plastik';

    public function label(): string
    {
        return match ($this) {
            self::Portrait => 'Dikey A6 (10,5 × 14,8 cm) — A4\'e 4 kart',
            self::Landscape => 'Yatay (9 × 6,4 cm) — A4\'e 8 kart',
            self::Card => 'Plastik kart (8,6 × 5,4 cm) — A4\'e 10 kart',
        };
    }

    /**
     * Kartın milimetre ölçüsü ve A4 sayfasındaki dizilişi.
     *
     * @return array{width: float, height: float, columns: int, rows: int}
     */
    public function geometry(): array
    {
        return match ($this) {
            self::Portrait => ['width' => 100, 'height' => 141, 'columns' => 2, 'rows' => 2],
            self::Landscape => ['width' => 90, 'height' => 65, 'columns' => 2, 'rows' => 4],
            self::Card => ['width' => 86, 'height' => 54, 'columns' => 2, 'rows' => 5],
        };
    }

    /**
     * Tasarım sayfasındaki kart çiziminin piksel genişliği (dikey 210, yatay 270, plastik 258);
     * PDF'te ölçüler bu oranla milimetreye çevrilir.
     */
    public function designWidth(): int
    {
        return match ($this) {
            self::Portrait => 210,
            self::Landscape => 270,
            self::Card => 258,
        };
    }

    public function perPage(): int
    {
        $g = $this->geometry();

        return $g['columns'] * $g['rows'];
    }
}
