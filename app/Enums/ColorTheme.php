<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kullanıcının seçebileceği renk temaları (resources/css/app.css → [data-theme=...]).
 * Açık / koyu mod bundan bağımsızdır (appearance çerezi).
 */
enum ColorTheme: string
{
    use HasOptions;

    case Haremeyn = 'haremeyn';
    case Kurumsal = 'kurumsal';
    case Kum = 'kum';

    public const DEFAULT = self::Haremeyn;

    public function label(): string
    {
        return match ($this) {
            self::Haremeyn => 'Haremeyn',
            self::Kurumsal => 'Sade kurumsal',
            self::Kum => 'Kum ve bakır',
        };
    }

    /**
     * Sayfa yüklenirken (CSS gelmeden) gösterilecek arka plan; beyaz parlamayı önler.
     */
    public function background(bool $dark): string
    {
        return match ($this) {
            self::Haremeyn => $dark ? '#0b1512' : '#fbf9f4',
            self::Kurumsal => $dark ? '#0b1220' : '#f6f8fb',
            self::Kum => $dark ? '#1a1512' : '#f7f1e8',
        };
    }
}
