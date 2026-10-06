<?php

namespace App\Enums;

/**
 * Bir yolcunun bir hazırlık maddesindeki durumu (boşsa satır yok = bekliyor).
 */
enum ReadinessStatus: string
{
    case Done = 'tamam';
    case Problem = 'sorun';
}
