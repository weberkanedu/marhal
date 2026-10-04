<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TourStatus: string
{
    use HasOptions;

    case Draft = 'taslak';
    case OnSale = 'satista';
    case Closed = 'kapandi';
    case Completed = 'tamamlandi';
    case Cancelled = 'iptal';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::OnSale => 'Satışta',
            self::Closed => 'Satış kapandı',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal',
        };
    }

    /**
     * Paketin "aynı anda aktif tur" limitine sayılan durumlar.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Draft, self::OnSale, self::Closed];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
