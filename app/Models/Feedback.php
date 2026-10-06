<?php

namespace App\Models;

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
 * @property string $status
 * @property string|null $reply
 * @property Carbon|null $replied_at
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 */
class Feedback extends Model
{
    use BelongsToTenant;

    protected $table = 'feedback';

    protected $guarded = ['id', 'tenant_id', 'status', 'reply', 'replied_at'];

    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'rating' => 'integer',
            'wants_reply' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
