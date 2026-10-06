<?php

namespace App\Actions\Tours;

use App\Models\Tour;
use App\Models\TourProgramItem;
use Illuminate\Validation\ValidationException;

/**
 * Tur programına etkinlik ekler / düzeltir. Gün turun tarihleri içinde olmalı (aile ekranında o gün görünür).
 */
class SaveProgramItem
{
    /**
     * @param  array{day: string, time?: string|null, title: string, place?: string|null}  $data
     */
    public function handle(Tour $tour, array $data, ?TourProgramItem $item = null): TourProgramItem
    {
        if ($data['day'] < $tour->start_date->toDateString() || $data['day'] > $tour->end_date->toDateString()) {
            throw ValidationException::withMessages(['day' => 'Gün turun tarihleri içinde olmalı.']);
        }

        $item ??= new TourProgramItem(['tour_id' => $tour->id]);
        $item->fill([
            'day' => $data['day'],
            'time' => $data['time'] ?? null,
            'title' => trim($data['title']),
            'place' => isset($data['place']) && trim($data['place']) !== '' ? trim($data['place']) : null,
        ])->save();

        return $item;
    }
}
