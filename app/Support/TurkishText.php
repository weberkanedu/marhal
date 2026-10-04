<?php

namespace App\Support;

/**
 * Türkçe büyük/küçük harf dönüşümü. mb_strtoupper "i"yi "I" yapar; Türkçede "İ" olmalı
 * (pasaport, vize ve havayolu listelerinde isimler pasaportla birebir aynı olmalı).
 */
final class TurkishText
{
    public static function upper(string $value): string
    {
        return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $value), 'UTF-8');
    }

    public static function lower(string $value): string
    {
        return mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $value), 'UTF-8');
    }

    /**
     * Türkçe alfabe sırasıyla karşılaştırma (Ç C'den, Ö O'dan sonra; büyük/küçük harf duyarsız).
     */
    public static function compare(string $a, string $b): int
    {
        static $collator = null;
        $collator ??= new \Collator('tr_TR');
        $collator->setStrength(\Collator::SECONDARY);

        return (int) $collator->compare($a, $b);
    }
}
