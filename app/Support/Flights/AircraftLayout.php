<?php

namespace App\Support\Flights;

/**
 * Uçak kabin düzeni: "3-3", "2-4-2", "3-4-3", "3-3-3" gibi koltuk grupları, ilk / son sıra ve
 * acil çıkış sıraları. Koltuk adı sıra + harf ("14C"). Ekran çizimi, kurallar ve liste aynı hesabı kullanır.
 */
final readonly class AircraftLayout
{
    /** Havayollarının yaygın harf düzenleri ("I" harfi kullanılmaz). */
    private const LETTERS = [
        '3-3' => 'ABC-DEF',
        '2-4-2' => 'AC-DEFG-HK',
        '3-4-3' => 'ABC-DEFG-HJK',
        '3-3-3' => 'ABC-DEF-HJK',
        '2-2' => 'AC-DF',
        '2-3-2' => 'AC-DEF-HK',
    ];

    /** Acil çıkışta oturamayacak yaş sınırları (havayolu kuralı). */
    public const EXIT_MIN_AGE = 15;

    public const EXIT_MAX_AGE = 65;

    /**
     * @param  list<int>  $exitRows
     */
    public function __construct(
        public string $cabin,
        public int $firstRow,
        public int $lastRow,
        public array $exitRows = [],
    ) {}

    /**
     * @param  object{cabin: string|null, first_row: int|null, last_row: int|null, exit_rows: array<int, int|string>|null}  $source
     */
    public static function of(object $source): ?self
    {
        if ($source->cabin === null || $source->last_row === null) {
            return null;
        }

        return new self(
            $source->cabin,
            (int) ($source->first_row ?? 1),
            (int) $source->last_row,
            array_values(array_map('intval', $source->exit_rows ?? [])),
        );
    }

    public static function validCabin(string $cabin): bool
    {
        return preg_match('/^[1-4](-[1-4]){1,2}$/', $cabin) === 1;
    }

    /**
     * Koridorla ayrılan harf grupları: [["A","B","C"], ["D","E","F"]].
     *
     * @return list<list<string>>
     */
    public function groups(): array
    {
        $sizes = array_map('intval', explode('-', $this->cabin));
        $pattern = self::LETTERS[$this->cabin] ?? null;

        if ($pattern !== null) {
            return array_map(fn (string $g) => str_split($g), explode('-', $pattern));
        }

        // Tanımsız düzen: harfler sırayla (I atlanır).
        $letters = str_split('ABCDEFGHJKLM');
        $groups = [];
        foreach ($sizes as $size) {
            $groups[] = array_splice($letters, 0, $size);
        }

        return $groups;
    }

    /**
     * @return list<int>
     */
    public function rows(): array
    {
        return range($this->firstRow, max($this->firstRow, $this->lastRow));
    }

    /**
     * @return list<string>
     */
    public function seats(): array
    {
        $letters = array_merge(...$this->groups());
        $seats = [];

        foreach ($this->rows() as $row) {
            foreach ($letters as $letter) {
                $seats[] = $row.$letter;
            }
        }

        return $seats;
    }

    public function seatCount(): int
    {
        return count($this->rows()) * count(array_merge(...$this->groups()));
    }

    public function has(string $seat): bool
    {
        return in_array(strtoupper($seat), $this->seats(), true);
    }

    public function row(string $seat): int
    {
        return (int) $seat;
    }

    public function isExitRow(int $row): bool
    {
        return in_array($row, $this->exitRows, true);
    }

    /**
     * Yan yana koltuklar (aynı sıra, aynı grup, bitişik harf).
     *
     * @return list<string>
     */
    public function neighbours(string $seat): array
    {
        $row = $this->row($seat);
        $letter = substr(strtoupper($seat), -1);

        foreach ($this->groups() as $group) {
            $index = array_search($letter, $group, true);

            if ($index !== false) {
                return array_values(array_map(
                    fn (string $l) => $row.$l,
                    array_filter([$group[$index - 1] ?? null, $group[$index + 1] ?? null]),
                ));
            }
        }

        return [];
    }

    /**
     * Örn. "3-3 · 30 sıra · 180 koltuk".
     */
    public function label(): string
    {
        return "{$this->cabin} · ".count($this->rows())." sıra · {$this->seatCount()} koltuk";
    }

    /**
     * @return array{cabin: string, first_row: int, last_row: int, exit_rows: list<int>}
     */
    public function toAttributes(): array
    {
        return [
            'cabin' => $this->cabin,
            'first_row' => $this->firstRow,
            'last_row' => $this->lastRow,
            'exit_rows' => $this->exitRows,
        ];
    }
}
