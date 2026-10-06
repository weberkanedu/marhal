<?php

namespace App\Http\Controllers;

use App\Actions\Feedback\ReplyToFeedback;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Görüşünü paylaş": her acente kullanıcısı (rehber dahil) gönderebilir ve "Gönderdiklerim"de izler;
 * platform yöneticisi listeler ve yanıtlar (ReplyToFeedback).
 */
class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(FeedbackType::class)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:3', 'max:3000'],
            'screen' => ['nullable', 'string', 'max:255'],
            'wants_reply' => ['boolean'],
        ], [], ['type' => 'tür', 'rating' => 'puan', 'message' => 'mesaj']);

        $feedback = Feedback::create([
            ...$data,
            // Yalnız adres yolu saklanır (sorgu parametresi kişisel veri taşıyabilir).
            'screen' => isset($data['screen']) ? strtok($data['screen'], '?') ?: null : null,
            'user_id' => $request->user()?->getKey(),
        ]);

        // "Görüşünü paylaş" paneli teşekkür ekranında takip numarasını gösterir.
        Inertia::flash('feedback', ['no' => $feedback->id]);

        return back();
    }

    /**
     * "Gönderdiklerim": kullanıcının kendi gönderdikleri (başkasınınki hiç gelmez), durum ve yanıt.
     * Açılınca yanıtlar görülmüş sayılır (düğmedeki nokta söner).
     */
    public function mine(Request $request, ReplyToFeedback $replies): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = Feedback::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Feedback $f) => [
                'id' => $f->id,
                'tracking' => $f->trackingNo(),
                'type_label' => $f->type->label(),
                'rating' => $f->rating,
                'message' => $f->message,
                'status' => $f->status->value,
                'status_label' => $f->status->label(),
                'reply' => $f->reply,
                'replied_at' => $f->replied_at?->toIso8601String(),
                'unseen' => $f->status === FeedbackStatus::Replied && $f->reply_seen_at === null,
                'created_at' => $f->created_at->toIso8601String(),
            ]);

        $replies->markSeen($user);

        return response()->json(['items' => $items]);
    }

    /**
     * Platform paneli → Geri bildirimler: gelen kutusu, yanıtlanmamışlar önce.
     */
    public function index(): Response
    {
        return Inertia::render('platform/Feedback', [
            'items' => Feedback::query()
                ->with(['tenant:id,name', 'user:id,name'])
                ->orderByRaw('case when status = ? then 0 else 1 end', [FeedbackStatus::New->value])
                ->latest()
                ->paginate(50)
                ->through(fn (Feedback $f) => [
                    'id' => $f->id,
                    'tracking' => $f->trackingNo(),
                    'tenant' => $f->tenant->name,
                    'user' => $f->user?->name,
                    'type' => $f->type->value,
                    'type_label' => $f->type->label(),
                    'rating' => $f->rating,
                    'message' => $f->message,
                    'screen' => $f->screen,
                    'wants_reply' => $f->wants_reply,
                    'status' => $f->status->value,
                    'reply' => $f->reply,
                    'replied_at' => $f->replied_at?->toIso8601String(),
                    'created_at' => $f->created_at->toIso8601String(),
                ]),
            'newCount' => Feedback::query()->where('status', FeedbackStatus::New)->count(),
        ]);
    }

    public function reply(Request $request, Feedback $feedback, ReplyToFeedback $replies): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'min:2', 'max:3000']], [], ['reply' => 'yanıt']);

        /** @var User $actor */
        $actor = $request->user();
        $replies->handle($feedback, $data['reply'], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Yanıt kaydedildi · '.($feedback->user->name ?? 'kullanıcı').' "Gönderdiklerim"de görecek.']);

        return back();
    }
}
