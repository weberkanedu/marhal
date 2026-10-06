<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Acente kullanıcısının ürün ekibine ilettiği görüş. Acente yalnız kendi kayıtlarını görür;
 * platform yöneticisi (acentesiz) hepsini görür.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $user_id
 * @property FeedbackType $type
 * @property int|null $rating
 * @property string $message
 * @property string|null $screen
 * @property bool $wants_reply
 * @property FeedbackStatus $status
 * @property string|null $reply
 * @property Carbon|null $replied_at
 * @property int|null $replied_by
 * @property Carbon|null $reply_seen_at
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 */
class Feedback extends Model
{
    use BelongsToTenant;

    protected $table = 'feedback';

    protected $guarded = ['id', 'tenant_id', 'status', 'reply', 'replied_at', 'replied_by', 'reply_seen_at'];

    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'rating' => 'integer',
            'wants_reply' => 'boolean',
            'status' => FeedbackStatus::class,
            'replied_at' => 'datetime',
            'reply_seen_at' => 'datetime',
        ];
    }

    /**
     * Takip numarası (teşekkür ekranı, platform ve "Gönderdiklerim" aynı biçimi gösterir).
     */
    public function trackingNo(): string
    {
        return 'GB-'.$this->created_at->format('Y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
