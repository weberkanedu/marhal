<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir kişinin bir tura katılımı. Fiyat ve bakiye buradadır.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property string|null $group_id
 * @property string $person_id
 * @property RoomType|null $room_type
 * @property string $price
 * @property string $discount
 * @property string $currency
 * @property RegistrationStatus $status
 * @property Carbon|null $cancelled_at
 * @property Carbon $registered_at
 * @property-read Tour $tour
 * @property-read Group|null $group
 * @property-read Person $person
 */
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    /**
     * Ödenen toplam: tahsilatlar eksi iadeler (kayıt para biriminde).
     */
    private const PAID_TOTAL_SQL = 'COALESCE(SUM(CASE WHEN type = ? THEN -amount_in_registration_currency ELSE amount_in_registration_currency END), 0)';

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'room_type' => RoomType::class,
            'status' => RegistrationStatus::class,
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Tur içindeki grubu (henüz gruba atanmamışsa null).
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Konaklamalardaki odaları (Mekke, Medine…).
     *
     * @return HasMany<RoomAssignment, $this>
     */
    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Listelerde N+1 olmadan ödenen tutarı `paid_total` olarak ekler.
     *
     * @param  Builder<Registration>  $query
     * @return Builder<Registration>
     */
    public function scopeWithPaidTotal(Builder $query): Builder
    {
        return $query->addSelect([
            'paid_total' => Payment::query()
                ->withoutGlobalScopes()
                ->selectRaw(self::PAID_TOTAL_SQL, [PaymentType::Refund->value])
                ->whereColumn('payments.registration_id', 'registrations.id')
                ->whereNull('payments.deleted_at'),
        ]);
    }

    /**
     * @return numeric-string
     */
    public function netPrice(): string
    {
        return Money::sub($this->price, $this->discount);
    }

    /**
     * @return numeric-string
     */
    public function paidTotal(): string
    {
        if (array_key_exists('paid_total', $this->attributes)) {
            return Money::of($this->attributes['paid_total']);
        }

        $sum = $this->payments()->toBase()
            ->selectRaw(self::PAID_TOTAL_SQL.' as total', [PaymentType::Refund->value])
            ->first()?->total;

        return Money::of($sum);
    }

    /**
     * @return numeric-string
     */
    public function balance(): string
    {
        return Money::sub($this->netPrice(), $this->paidTotal());
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('due_date');
    }

    /**
     * Vadesi bugün veya daha önce olan taksitlerin toplamını `due_total` olarak ekler.
     *
     * @param  Builder<Registration>  $query
     * @return Builder<Registration>
     */
    public function scopeWithDueTotal(Builder $query): Builder
    {
        return $query->addSelect([
            'due_total' => Installment::query()
                ->withoutGlobalScopes()
                ->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('installments.registration_id', 'registrations.id')
                ->whereDate('installments.due_date', '<=', today()),
        ]);
    }

    /**
     * Vadesi gelmiş taksitlerin toplamı.
     *
     * @return numeric-string
     */
    public function dueTotal(): string
    {
        if (array_key_exists('due_total', $this->attributes)) {
            return Money::of($this->attributes['due_total']);
        }

        return Money::of($this->installments()->whereDate('due_date', '<=', today())->sum('amount'));
    }

    /**
     * Gecikmiş borç: vadesi gelen taksitler − ödenen (negatifse 0).
     * Taksit planı yoksa gecikme hesaplanmaz.
     *
     * @return numeric-string
     */
    public function overdue(): string
    {
        $overdue = Money::sub($this->dueTotal(), $this->paidTotal());

        return bccomp($overdue, '0', 2) > 0 ? $overdue : '0.00';
    }
}
