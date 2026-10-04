<?php

namespace App\Support\Security;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Kimlik / pasaport numarası gibi alanlar için şifreleme ve arama hash'i.
 * APP_KEY'den bağımsız anahtarlar kullanır (SPEC.md §5).
 */
class SensitiveData
{
    private ?Encrypter $encrypter = null;

    public function __construct(
        private readonly ?string $encryptionKey,
        private readonly ?string $hashKey,
    ) {}

    public function encrypt(string $value): string
    {
        return $this->encrypter()->encryptString($value);
    }

    public function decrypt(string $payload): string
    {
        return $this->encrypter()->decryptString($payload);
    }

    /**
     * Aynı değer her zaman aynı hash'i üretir; arama ve tekrar kontrolü için kullanılır.
     */
    public function hash(string $value): string
    {
        if (blank($this->hashKey)) {
            throw new RuntimeException('HASH_KEY tanımlı değil. `php artisan marhal:keys` çalıştırın.');
        }

        return hash_hmac('sha256', $this->normalize($value), $this->parseKey($this->hashKey));
    }

    public function normalize(string $value): string
    {
        return Str::upper(preg_replace('/\s+/', '', $value) ?? '');
    }

    /**
     * "12345678901" → "*******8901"
     */
    public function mask(?string $value, int $visible = 4): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = mb_strlen($value);

        if ($length <= $visible) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $visible).mb_substr($value, -$visible);
    }

    private function encrypter(): Encrypter
    {
        if (blank($this->encryptionKey)) {
            throw new RuntimeException('ENCRYPTION_KEY tanımlı değil. `php artisan marhal:keys` çalıştırın.');
        }

        return $this->encrypter ??= new Encrypter($this->parseKey($this->encryptionKey), 'AES-256-CBC');
    }

    private function parseKey(string $key): string
    {
        return Str::startsWith($key, 'base64:') ? base64_decode(Str::after($key, 'base64:')) : $key;
    }
}
