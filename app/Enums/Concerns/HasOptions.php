<?php

namespace App\Enums\Concerns;

/**
 * Arayüzdeki seçim kutuları için [{value, label}] listesi üretir.
 * Kullanan enum bir `label(): string` metodu tanımlamalıdır.
 */
trait HasOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
