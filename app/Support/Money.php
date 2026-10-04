<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Para hesapları için bcmath yardımcıları (float yuvarlama hatası olmadan).
 * Tüm sonuçlar 2 basamaklı numeric-string döner.
 */
final class Money
{
    /**
     * @return numeric-string
     */
    public static function of(string|int|float|null $value): string
    {
        $value ??= 0;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Geçersiz tutar: {$value}");
        }

        return bcadd((string) $value, '0', 2);
    }

    /**
     * @return numeric-string
     */
    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::of($a), self::of($b), 2);
    }

    /**
     * @return numeric-string
     */
    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::of($a), self::of($b), 2);
    }

    /**
     * Kur çarpımı gibi işlemler için; çarpan 6 basamağa kadar hassasiyetle alınır.
     *
     * @return numeric-string
     */
    public static function mul(string|int|float|null $amount, string|int|float|null $factor): string
    {
        $factor ??= 0;

        if (! is_numeric($factor)) {
            throw new InvalidArgumentException("Geçersiz çarpan: {$factor}");
        }

        // bcmul keser; yarım yukarı yuvarlamak için 3. basamakta hesaplayıp yuvarlıyoruz.
        $raw = bcmul(self::of($amount), (string) $factor, 3);

        return bcadd($raw, bccomp($raw, '0', 3) >= 0 ? '0.005' : '-0.005', 2);
    }

    public static function isZero(string|int|float|null $value): bool
    {
        return bccomp(self::of($value), '0', 2) === 0;
    }
}
