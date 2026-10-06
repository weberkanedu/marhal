<?php

namespace Database\Seeders\Demo;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\Tenant;
use App\Models\User;

/**
 * Paketler 10c örnek verisi: acentelere şehir; demo yöneticinin iki geri bildirimi (biri yanıtlanmış,
 * "Gönderdiklerim"de ve düğmedeki yeni yanıt noktasında görülsün diye).
 */
class FeedbackReplyDemo
{
    private const CITIES = [
        'demo-turizm' => 'İstanbul',
        'nur-yolu-ornek' => 'Konya',
        'harem-tur-ornek' => 'Kayseri',
        'mina-seyahat-ornek' => 'Bursa',
    ];

    public function run(Tenant $tenant): string
    {
        foreach (self::CITIES as $slug => $city) {
            Tenant::query()->where('slug', $slug)->whereNull('city')->update(['city' => $city]);
        }

        $admin = User::query()->where('tenant_id', $tenant->id)->where('role', 'admin')->first();
        $platform = User::query()->where('role', 'super_admin')->first();

        if ($admin === null) {
            return 'Şehirler yazıldı; yönetici olmadığı için geri bildirim örneği eklenmedi.';
        }

        $this->feedback($tenant, $admin, FeedbackType::Suggestion, 5, '/stays',
            'Otomatik dağıtta aileleri aynı kata koyabilir miyiz? Yaşlılar katlar arasında kayboluyor.');

        $answered = $this->feedback($tenant, $admin, FeedbackType::Bug, 3, '/tours',
            'Plastik yaka kartında uzun soyadlar sığmıyor, iki satıra bölünmesi lazım.');
        $answered->forceFill([
            'status' => FeedbackStatus::Replied,
            'reply' => 'Teşekkür ederiz, konuyu inceledik. Bir sonraki güncellemede uzun soyadlar iki satıra bölünecek.',
            'replied_at' => now(),
            'replied_by' => $platform?->id,
        ])->save();

        return 'Acentelere şehir yazıldı; demo yöneticiye iki örnek geri bildirim eklendi (biri yanıtlanmış).';
    }

    private function feedback(Tenant $tenant, User $user, FeedbackType $type, int $rating, string $screen, string $message): Feedback
    {
        $feedback = new Feedback([
            'user_id' => $user->id,
            'type' => $type,
            'rating' => $rating,
            'message' => $message,
            'screen' => $screen,
            'wants_reply' => true,
        ]);
        $feedback->tenant_id = $tenant->id;
        $feedback->save();

        return $feedback;
    }
}
