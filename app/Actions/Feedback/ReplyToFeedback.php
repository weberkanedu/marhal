<?php

namespace App\Actions\Feedback;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\User;

/**
 * Platform yöneticisi geri bildirime yanıt yazar (ya da yanıtını düzeltir). Yanıt gönderenin "Gönderdiklerim"
 * bölümünde görünür ve düğmede yeni yanıt noktası yanar (reply_seen_at sıfırlanır). E-posta servisi
 * bağlanınca buradan e-posta da gönderilecek.
 */
class ReplyToFeedback
{
    public function handle(Feedback $feedback, string $reply, User $actor): Feedback
    {
        $feedback->forceFill([
            'reply' => trim($reply),
            'replied_at' => now(),
            'replied_by' => $actor->id,
            'reply_seen_at' => null,
            'status' => FeedbackStatus::Replied,
        ])->save();

        return $feedback;
    }

    /**
     * Kullanıcı "Gönderdiklerim"i açınca yanıtları görülmüş sayılır.
     */
    public function markSeen(User $user): void
    {
        Feedback::query()
            ->where('user_id', $user->id)
            ->where('status', FeedbackStatus::Replied)
            ->whereNull('reply_seen_at')
            ->update(['reply_seen_at' => now()]);
    }
}
