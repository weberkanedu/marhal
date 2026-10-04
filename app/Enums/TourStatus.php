<?php

namespace App\Enums;

enum TourStatus: string
{
    case Draft = 'taslak';
    case OnSale = 'satista';
    case Closed = 'kapandi';
    case Completed = 'tamamlandi';
    case Cancelled = 'iptal';

    /**
     * Paketin "aynı anda aktif grup" limitine sayılan durumlar.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Draft, self::OnSale, self::Closed];
    }
}
