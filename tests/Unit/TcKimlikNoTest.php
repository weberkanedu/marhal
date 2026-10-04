<?php

namespace Tests\Unit;

use App\Rules\TcKimlikNo;
use PHPUnit\Framework\TestCase;

class TcKimlikNoTest extends TestCase
{
    public function test_known_valid_number_passes(): void
    {
        // Algoritmayı sağlayan örnek numara (gerçek kişiye ait değildir).
        $this->assertTrue(TcKimlikNo::isValid('10000000146'));
    }

    public function test_invalid_numbers_fail(): void
    {
        $this->assertFalse(TcKimlikNo::isValid('12345678901'));
        $this->assertFalse(TcKimlikNo::isValid('01234567890'), 'İlk hane 0 olamaz.');
        $this->assertFalse(TcKimlikNo::isValid('1234567890'), '10 hane geçersiz.');
        $this->assertFalse(TcKimlikNo::isValid('1000000014a'));
    }

    public function test_generated_numbers_are_valid(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue(TcKimlikNo::isValid(TcKimlikNo::generate()));
        }
    }
}
