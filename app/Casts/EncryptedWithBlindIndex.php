<?php

namespace App\Casts;

use App\Support\Security\SensitiveData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Değeri şifreli saklar ve aynı anda `{alan}_hash` sütununa arama hash'ini yazar.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedWithBlindIndex implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(SensitiveData::class)->decrypt($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [$key => null, "{$key}_hash" => null];
        }

        $data = app(SensitiveData::class);
        $normalized = $data->normalize((string) $value);

        return [
            $key => $data->encrypt($normalized),
            "{$key}_hash" => $data->hash($normalized),
        ];
    }
}
