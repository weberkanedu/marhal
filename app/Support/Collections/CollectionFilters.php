<?php

namespace App\Support\Collections;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Tahsilat ekranı ve tahsilat raporlarının ortak filtreleri (sekme, tur, tarih aralığı).
 */
final readonly class CollectionFilters
{
    public const TABS = ['borclu', 'tamamlanan', 'tahsilatlar'];

    public function __construct(
        public string $tab,
        public ?string $tourId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $tab = $request->query('tab');
        $tour = $request->query('tour');

        return new self(
            tab: is_string($tab) && in_array($tab, self::TABS, true) ? $tab : 'borclu',
            tourId: is_string($tour) && $tour !== '' ? $tour : null,
            from: self::date($request->query('from')) ?? today()->startOfMonth()->toImmutable(),
            to: self::date($request->query('to')) ?? today()->toImmutable(),
        );
    }

    /**
     * @return array{tab: string, tour: string|null, from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'tab' => $this->tab,
            'tour' => $this->tourId,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value)?->startOfDay() ?: null;
    }
}
