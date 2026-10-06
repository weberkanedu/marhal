<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform geneli ayar (anahtar → JSON değer). Okuma / yazma `App\Support\Security\SecuritySettings` üzerinden.
 *
 * @property string $key
 * @property mixed $value
 */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
