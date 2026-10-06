<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeedbackType: string
{
    use HasOptions;

    case Suggestion = 'oneri';
    case Bug = 'hata';
    case Question = 'soru';
    case Praise = 'begeni';

    public function label(): string
    {
        return match ($this) {
            self::Suggestion => 'Öneri',
            self::Bug => 'Sorun',
            self::Question => 'Soru',
            self::Praise => 'Teşekkür',
        };
    }
}
