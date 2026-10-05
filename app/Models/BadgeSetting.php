<?php

namespace App\Models;

use App\Enums\BadgeSize;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Acentenin yaka kartı ayarı (acente başına tek satır; yoksa varsayılanlar).
 *
 * @property string $id
 * @property string $tenant_id
 * @property BadgeSize $size
 * @property list<string> $fields ön yüz: photo, hotels, bus, guide, qr
 * @property list<string> $back_languages arka yüz: tr, en, ar
 * @property bool $back_side
 * @property bool $health_note
 */
class BadgeSetting extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    /** Ön yüzde gösterilebilecek alanlar (sıra = kartta sıra). */
    public const FIELDS = ['photo', 'hotels', 'bus', 'guide', 'qr'];

    public const LANGUAGES = ['tr', 'en', 'ar'];

    protected $guarded = ['id', 'tenant_id'];

    protected $attributes = [
        'size' => 'yatay',
        'fields' => '["photo","hotels","bus","guide","qr"]',
        'back_languages' => '["tr","en","ar"]',
        'back_side' => true,
        'health_note' => false,
    ];

    protected function casts(): array
    {
        return [
            'size' => BadgeSize::class,
            'fields' => 'array',
            'back_languages' => 'array',
            'back_side' => 'boolean',
            'health_note' => 'boolean',
        ];
    }

    /**
     * Acentenin ayarı; kaydedilmemişse varsayılanlarla (kaydetmeden).
     */
    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    public function shows(string $field): bool
    {
        return in_array($field, $this->fields, true);
    }
}
