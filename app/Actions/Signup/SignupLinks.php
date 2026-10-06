<?php

namespace App\Actions\Signup;

use App\Models\SignupLink;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Turun ön kayıt linki: turda tek etkin link olur. "Yenile" eskisini kapatıp yeni link verir
 * (yanlış yere gönderilmiş linkin önüne geçmek için).
 */
class SignupLinks
{
    public function current(Tour $tour): ?SignupLink
    {
        return SignupLink::query()->where('tour_id', $tour->id)->where('is_active', true)->latest()->first();
    }

    public function create(Tour $tour, User $by): SignupLink
    {
        $this->close($tour);

        $token = Str::random(24);

        return SignupLink::query()->create([
            'tour_id' => $tour->id,
            'token' => $token,
            'token_hash' => SignupLink::hash($token),
            'created_by' => $by->getKey(),
        ]);
    }

    public function close(Tour $tour): void
    {
        SignupLink::query()->where('tour_id', $tour->id)->where('is_active', true)->update(['is_active' => false]);
    }
}
