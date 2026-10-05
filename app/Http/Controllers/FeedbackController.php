<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackType;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Görüşünü paylaş": her acente kullanıcısı (rehber dahil) gönderebilir; platform yöneticisi listeler.
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
        ], [], ['type' => 'tür', 'rating' => 'puan', 'message' => 'mesaj']);

        $feedback = Feedback::create([
            ...$data,
            // Yalnız adres yolu saklanır (sorgu parametresi kişisel veri taşıyabilir).
            'screen' => isset($data['screen']) ? strtok($data['screen'], '?') ?: null : null,
            'user_id' => $request->user()?->getKey(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Teşekkürler! Görüşünüz ürün ekibine iletildi. Takip no: #{$feedback->id}",
        ]);

        return back();
    }

    /**
     * Platform paneli: gelen görüşler (yanıtlama paketler aşamasında gelecek).
     */
    public function index(): Response
    {
        return Inertia::render('platform/Feedback', [
            'items' => Feedback::query()
                ->with(['tenant:id,name', 'user:id,name'])
                ->latest()
                ->paginate(50)
                ->through(fn (Feedback $f) => [
                    'id' => $f->id,
                    'tenant' => $f->tenant->name,
                    'user' => $f->user?->name,
                    'type' => $f->type->value,
                    'type_label' => $f->type->label(),
                    'rating' => $f->rating,
                    'message' => $f->message,
                    'screen' => $f->screen,
                    'status' => $f->status,
                    'created_at' => $f->created_at->toIso8601String(),
                ]),
        ]);
    }
}
