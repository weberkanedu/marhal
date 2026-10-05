<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kullanıcının seçebileceği temalar (resources/css/app.css → [data-theme=...]).
 * Her tema kendi açık / koyu karakterini taşır; ayrı bir açık/koyu düğmesi yok.
 */
enum ColorTheme: string
{
    use HasOptions;

    case Zumrut = 'zumrut';
    case Safak = 'safak';

    public const DEFAULT = self::Zumrut;

    public function label(): string
    {
        return match ($this) {
            self::Zumrut => 'Gece Zümrüdü',
            self::Safak => 'Şafak',
        };
    }

    public function isDark(): bool
    {
        return $this === self::Zumrut;
    }

    /**
     * Sayfa yüklenirken (CSS gelmeden) gösterilecek arka plan; parlamayı önler.
     */
    public function background(): string
    {
        return match ($this) {
            self::Zumrut => '#0b1b16',
            self::Safak => '#eef3f1',
        };
    }
}
