<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. Kimlik Numarası algoritma kontrolü (11 hane, ilk hane 0 olamaz, 10. ve 11. hane kontrol basamağı).
 */
class TcKimlikNo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('Geçerli bir T.C. Kimlik Numarası giriniz.');
        }
    }

    public static function isValid(string $value): bool
    {
        if (! preg_match('/^[1-9][0-9]{10}$/', $value)) {
            return false;
        }

        $d = array_map('intval', str_split($value));

        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        $tenth = (($odd * 7) - $even) % 10;
        if ($tenth < 0) {
            $tenth += 10;
        }

        $eleventh = (array_sum(array_slice($d, 0, 10))) % 10;

        return $d[9] === $tenth && $d[10] === $eleventh;
    }

    /**
     * Test ve demo verisi için geçerli rastgele numara üretir.
     */
    public static function generate(): string
    {
        $d = [random_int(1, 9)];
        for ($i = 1; $i < 9; $i++) {
            $d[] = random_int(0, 9);
        }

        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        $d[9] = ((($odd * 7) - $even) % 10 + 10) % 10;
        $d[10] = array_sum($d) % 10;

        return implode('', $d);
    }
}
