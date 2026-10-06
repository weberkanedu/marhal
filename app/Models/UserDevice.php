<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanıcının giriş yaptığı cihaz (tarayıcı). Tarayıcıdaki kalıcı çerezin yalnız özeti saklanır.
 * Kişi menüsü → Güvenlik → "Cihazlarım"da listelenir ve kaldırılabilir.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $label
 * @property string|null $last_ip
 * @property string|null $last_network
 * @property Carbon $last_seen_at
 * @property Carbon $created_at
 */
class UserDevice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
