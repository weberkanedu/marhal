<?php

namespace Tests\Unit;

use App\Support\TurkishText;
use PHPUnit\Framework\TestCase;

class TurkishTextTest extends TestCase
{
    public function test_uppercase_follows_turkish_rules(): void
    {
        $this->assertSame('ŞAHİN', TurkishText::upper('Şahin'));
        $this->assertSame('IŞIK', TurkishText::upper('ışık'));
        $this->assertSame('ERKEKLİ ÇİÇEK', TurkishText::upper('Erkekli Çiçek'));
    }

    public function test_lowercase_follows_turkish_rules(): void
    {
        $this->assertSame('ılgaz ığdır', TurkishText::lower('ILGAZ IĞDIR'));
        $this->assertSame('istanbul', TurkishText::lower('İSTANBUL'));
    }

    public function test_sorting_uses_turkish_alphabet(): void
    {
        $names = ['Özberk', 'Topçuoğlu', 'Oral', 'Çelik', 'Cengiz', 'Şahin', 'Sarı', 'İnce', 'Işık'];
        usort($names, [TurkishText::class, 'compare']);

        $this->assertSame(['Cengiz', 'Çelik', 'Işık', 'İnce', 'Oral', 'Özberk', 'Sarı', 'Şahin', 'Topçuoğlu'], $names);
    }
}
