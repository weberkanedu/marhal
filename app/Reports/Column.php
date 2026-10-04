<?php

namespace App\Reports;

/**
 * Rapor sütunu. `type` biçimlendirmeyi belirler: text, date, money, number.
 */
final readonly class Column
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
        public ?float $pdfWidth = null,
    ) {}

    public static function text(string $key, string $label, ?float $pdfWidth = null): self
    {
        return new self($key, $label, 'text', $pdfWidth);
    }

    public static function date(string $key, string $label): self
    {
        return new self($key, $label, 'date', 11);
    }

    public static function money(string $key, string $label): self
    {
        return new self($key, $label, 'money', 12);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, $label, 'number', 6);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['money', 'number'], true);
    }
}
