<?php

namespace App\Support\Imports;

use App\Enums\Gender;
use App\Enums\RoomType;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Excel'den gelen yolcu satırlarını sistem alanlarına çevirir. Acentelerin kendi Excel'leri farklı
 * başlıklar ve biçimler kullanabilir; başlıklar eş anlamlılarıyla, değerler yaygın yazımlarıyla tanınır.
 * Tanınmayan değer olduğu gibi bırakılır, doğrulama hata olarak gösterir (sessizce değiştirilmez).
 */
final class PersonRowNormalizer
{
    /**
     * Sadeleştirilmiş başlık → alan. Yeni bir eş anlamlı eklemek için buraya bir satır yeter.
     *
     * @var array<string, string>
     */
    public const HEADERS = [
        'ad' => 'first_name', 'adi' => 'first_name', 'isim' => 'first_name', 'adiniz' => 'first_name',
        'soyad' => 'last_name', 'soyadi' => 'last_name', 'soyisim' => 'last_name',
        'cinsiyet' => 'gender', 'cinsiyeti' => 'gender',
        'dogumtarihi' => 'birth_date', 'dogum' => 'birth_date',
        'uyruk' => 'nationality', 'uyrugu' => 'nationality', 'ulke' => 'nationality',
        'tckimlikno' => 'national_id', 'tc' => 'national_id', 'tckn' => 'national_id', 'tcno' => 'national_id',
        'kimlikno' => 'national_id', 'tckimliknumarasi' => 'national_id',
        'pasaportno' => 'passport_no', 'pasaport' => 'passport_no', 'pasaportnumarasi' => 'passport_no',
        'pasaportverilistarihi' => 'passport_issue_date', 'pasaportverilis' => 'passport_issue_date',
        'pasaportbitistarihi' => 'passport_expiry_date', 'pasaportgecerliliktarihi' => 'passport_expiry_date',
        'pasaportsonkullanmatarihi' => 'passport_expiry_date', 'pasaportbitis' => 'passport_expiry_date',
        'telefon' => 'phone', 'tel' => 'phone', 'gsm' => 'phone', 'cep' => 'phone', 'ceptelefonu' => 'phone', 'telefonno' => 'phone',
        'eposta' => 'email', 'email' => 'email', 'mail' => 'email',
        'adres' => 'address',
        'acildurumkisisi' => 'emergency_contact_name', 'acildurumkisi' => 'emergency_contact_name', 'yakini' => 'emergency_contact_name',
        'acildurumtelefonu' => 'emergency_contact_phone', 'acildurumtel' => 'emergency_contact_phone', 'yakinitelefonu' => 'emergency_contact_phone',
        'odatipi' => 'room_type', 'oda' => 'room_type',
        'ucret' => 'price', 'fiyat' => 'price', 'tutar' => 'price',
        'kvkkonayi' => 'kvkk', 'kvkk' => 'kvkk', 'acikriza' => 'kvkk',
        'notlar' => 'notes', 'not' => 'notes', 'aciklama' => 'notes',
    ];

    /**
     * Başlık satırından sütun sırası → alan eşlemesi (tanınmayan sütunlar atlanır).
     *
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    public static function mapHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $key = self::simplify((string) $header);

            if (isset(self::HEADERS[$key]) && ! in_array(self::HEADERS[$key], $map, true)) {
                $map[$index] = self::HEADERS[$key];
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, string>  $map
     * @return array<string, mixed>|null boş satırsa null
     */
    public static function row(array $row, array $map): ?array
    {
        $data = [];

        foreach ($map as $index => $field) {
            $value = $row[$index] ?? null;
            $data[$field] = is_string($value) ? trim($value) : $value;
        }

        if (collect($data)->every(fn ($v) => $v === null || $v === '')) {
            return null;
        }

        $nationality = self::nationality($data['nationality'] ?? null);

        return [
            'first_name' => self::text($data['first_name'] ?? null),
            'last_name' => self::text($data['last_name'] ?? null),
            'gender' => self::gender($data['gender'] ?? null),
            'birth_date' => self::date($data['birth_date'] ?? null),
            'nationality' => $nationality,
            'national_id' => self::digits($data['national_id'] ?? null),
            'passport_no' => ($p = self::text($data['passport_no'] ?? null)) !== null ? strtoupper(str_replace(' ', '', $p)) : null,
            'passport_issue_date' => self::date($data['passport_issue_date'] ?? null),
            'passport_expiry_date' => self::date($data['passport_expiry_date'] ?? null),
            'phone' => self::phone($data['phone'] ?? null),
            'email' => self::text($data['email'] ?? null),
            'address' => self::text($data['address'] ?? null),
            'emergency_contact_name' => self::text($data['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => self::phone($data['emergency_contact_phone'] ?? null),
            'notes' => self::text($data['notes'] ?? null),
            'room_type' => self::roomType($data['room_type'] ?? null),
            'price' => self::price($data['price'] ?? null),
            'kvkk' => self::yes($data['kvkk'] ?? null),
        ];
    }

    /**
     * "T.C. Kimlik No" → "tckimlikno"; "Doğum Tarihi" → "dogumtarihi".
     */
    public static function simplify(string $value): string
    {
        $value = mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $value), 'UTF-8');
        $value = strtr($value, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u']);

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(is_float($value) ? self::number($value) : (string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Excel büyük sayıları ondalıklı / bilimsel gösterir (12345678901 → 1.2345678901E+10).
     */
    private static function number(float|int $value): string
    {
        return is_int($value) || floor($value) === $value ? sprintf('%.0f', $value) : (string) $value;
    }

    private static function digits(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null ? null : (string) preg_replace('/\s+/', '', $text);
    }

    private static function gender(mixed $value): mixed
    {
        $key = self::simplify((string) $value);

        return match (true) {
            in_array($key, ['e', 'erkek', 'bay', 'm', 'male', 'man'], true) => Gender::Male->value,
            in_array($key, ['k', 'kadin', 'bayan', 'f', 'female', 'woman'], true) => Gender::Female->value,
            default => self::text($value),
        };
    }

    private static function nationality(mixed $value): string
    {
        $text = self::text($value);
        $key = self::simplify((string) $text);

        return match (true) {
            $text === null, in_array($key, ['tr', 'tc', 'turkiye', 'turk', 'turkey', 'tur'], true) => 'TR',
            default => strtoupper($text),
        };
    }

    /**
     * Excel tarih sayısı, GG.AA.YYYY, GG/AA/YYYY veya YYYY-AA-GG → YYYY-AA-GG. Tanınmazsa olduğu gibi.
     */
    private static function date(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_int($value) || is_float($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
            }

            $text = trim((string) $value);

            if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $text, $m)) {
                return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : $text;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text)) {
                return substr($text, 0, 10);
            }

            return $text;
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /**
     * Excel baştaki sıfırı siler: 5321234567 → 05321234567.
     */
    private static function phone(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text !== null && preg_match('/^5\d{9}$/', $text)) {
            return '0'.$text;
        }

        return $text;
    }

    private static function roomType(mixed $value): mixed
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $digit = (int) preg_replace('/\D/', '', $text);
        $match = collect(RoomType::cases())->first(fn (RoomType $t) => $t->capacity() === $digit);

        return $match?->value ?? $text;
    }

    private static function price(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        // "1.500,00" → 1500.00 ; "1500.5" → 1500.5
        $text = preg_replace('/[^\d.,]/', '', (string) $value) ?? '';
        if (str_contains($text, ',')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        }

        return is_numeric($text) ? $text : (string) $value;
    }

    private static function yes(mixed $value): bool
    {
        return in_array(self::simplify((string) $value), ['evet', 'e', 'x', '1', 'true', 'var', 'yes'], true);
    }
}
