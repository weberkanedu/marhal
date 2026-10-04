<?php

namespace App\Support\Buses;

/**
 * Otobüs koltuk düzeni: sol / sağ koltuk sayısı (2+2, 2+1, 1+1), sıra sayısı, arka sıra ve
 * orta kapı. Koltuklar önden arkaya, soldan sağa 1'den başlayarak numaralanır; kapı sırasının
 * sağ tarafında koltuk yoktur. Ekran çizimi, kurallar ve PDF aynı hesaplamayı kullanır.
 */
final readonly class BusLayout
{
    public const AISLE = 'aisle';

    public const DOOR = 'door';

    public function __construct(
        public int $left,
        public int $right,
        public int $rows,
        public int $backRow = 0,
        public ?int $doorRow = null,
    ) {}

    /**
     * @param  object{left_seats: int, right_seats: int, rows: int, back_row_seats: int, door_row: int|null}  $source
     */
    public static function of(object $source): self
    {
        return new self($source->left_seats, $source->right_seats, $source->rows, $source->back_row_seats, $source->door_row);
    }

    /**
     * Sıra sıra hücreler: koltuk numarası (int), koridor ('aisle'), kapı ('door') veya boşluk (null).
     *
     * @return list<array<int, int|string|null>>
     */
    public function grid(): array
    {
        $grid = [];
        $no = 1;

        for ($row = 1; $row <= $this->rows; $row++) {
            $cells = [];

            for ($i = 0; $i < $this->left; $i++) {
                $cells[] = $no++;
            }

            $cells[] = self::AISLE;

            for ($i = 0; $i < $this->right; $i++) {
                $cells[] = $row === $this->doorRow ? self::DOOR : $no++;
            }

            $grid[] = $cells;
        }

        if ($this->backRow > 0) {
            // Arka sıra koridoru da kapatır; genişlikten azsa sağı boş kalır.
            $width = $this->left + 1 + $this->right;
            $cells = [];
            for ($i = 0; $i < $width; $i++) {
                $cells[] = $i < $this->backRow ? $no++ : null;
            }
            $grid[] = $cells;
        }

        return $grid;
    }

    /**
     * @return list<int>
     */
    public function seatNumbers(): array
    {
        return array_values(array_filter(array_merge(...$this->grid()), 'is_int'));
    }

    public function seatCount(): int
    {
        return count($this->seatNumbers());
    }

    public function has(int $seat): bool
    {
        return $seat >= 1 && $seat <= $this->seatCount();
    }

    /**
     * Yan yana oturulan koltuk blokları (aynı sıranın aynı tarafı; arka sıra tek blok).
     * Otomatik dağıtma aileleri aynı bloğa, yan koltuk uyarısı da blok içine bakar.
     *
     * @return list<list<int>>
     */
    public function blocks(): array
    {
        $blocks = [];

        foreach ($this->grid() as $cells) {
            $current = [];

            foreach ($cells as $cell) {
                if (is_int($cell)) {
                    $current[] = $cell;
                } elseif ($cell === self::AISLE || $cell === self::DOOR) {
                    if ($current !== []) {
                        $blocks[] = $current;
                    }
                    $current = [];
                }
            }

            if ($current !== []) {
                $blocks[] = $current;
            }
        }

        return $blocks;
    }

    /**
     * Koltuğun yanındaki koltuklar (aynı blokta, bitişik).
     *
     * @return list<int>
     */
    public function neighbours(int $seat): array
    {
        foreach ($this->blocks() as $block) {
            $index = array_search($seat, $block, true);

            if ($index !== false) {
                return array_values(array_filter([$block[$index - 1] ?? null, $block[$index + 1] ?? null], 'is_int'));
            }
        }

        return [];
    }

    /**
     * Örn. "2+2 · 46 koltuk".
     */
    public function label(): string
    {
        return "{$this->left}+{$this->right} · {$this->seatCount()} koltuk";
    }

    /**
     * @return array{left_seats: int, right_seats: int, rows: int, back_row_seats: int, door_row: int|null}
     */
    public function toAttributes(): array
    {
        return [
            'left_seats' => $this->left,
            'right_seats' => $this->right,
            'rows' => $this->rows,
            'back_row_seats' => $this->backRow,
            'door_row' => $this->doorRow,
        ];
    }
}
