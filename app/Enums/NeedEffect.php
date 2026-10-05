<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * İhtiyacın yerleşim kurallarına etkisi (kural tek yerde: App\Support\Needs\NeedProfiles kullanılır).
 */
enum NeedEffect: string
{
    use HasOptions;

    /** Araçta ön bölge, otelde asansöre yakın oda, uçakta acil çıkışa oturamaz, havayoluna özel yardım. */
    case Mobility = 'hareket';

    /** Refakatçisiyle aynı oda / yan koltuk önerilir. */
    case Companion = 'refakat';

    /** Otel / havayolu yemek bildirimi. */
    case Diet = 'yemek';

    public function label(): string
    {
        return match ($this) {
            self::Mobility => 'Hareket güçlüğü kuralları',
            self::Companion => 'Refakatçi önerisi',
            self::Diet => 'Yemek bildirimi',
        };
    }
}
