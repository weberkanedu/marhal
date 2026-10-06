<?php

namespace App\Enums;

/**
 * Geri bildirimin durumu (sütun 2026-10-09'dan beri "yeni" ile başlıyor).
 */
enum FeedbackStatus: string
{
    case New = 'yeni';
    case Replied = 'yanitlandi';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Değerlendiriliyor',
            self::Replied => 'Yanıtlandı',
        };
    }
}
