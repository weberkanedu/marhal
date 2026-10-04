<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente yöneticisi için erişim kayıtları (KVKK: kim, ne zaman, hangi kayda ne yaptı).
 * Rota `role:admin` ile korunur; sadece kendi acentesinin kayıtları gösterilir.
 */
class AuditLogController extends Controller
{
    /** @var array<string, string> */
    private const ACTIONS = [
        'login' => 'Giriş',
        'login_failed' => 'Hatalı giriş denemesi',
        'view_sensitive' => 'Kimlik / pasaport görüntüleme',
        'export' => 'Rapor indirme',
        'create' => 'Oluşturma',
        'update' => 'Güncelleme',
        'delete' => 'Silme',
    ];

    /** @var array<string, string> */
    private const SUBJECTS = [
        Person::class => 'Yolcu',
        Tour::class => 'Tur',
        Group::class => 'Grup',
        Registration::class => 'Kayıt',
        Payment::class => 'Ödeme',
        Installment::class => 'Taksit',
    ];

    public function __invoke(Request $request, CurrentTenant $currentTenant): Response
    {
        $action = $request->query('action');
        $userId = $request->query('user');

        $logs = AuditLog::query()
            ->where('tenant_id', $currentTenant->id())
            ->when(is_string($action) && isset(self::ACTIONS[$action]), fn (Builder $q) => $q->where('action', $action))
            ->when(is_numeric($userId), fn (Builder $q) => $q->where('user_id', (int) $userId))
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at->toIso8601String(),
                'user' => $log->user?->name,
                'action' => self::ACTIONS[$log->action] ?? $log->action,
                'action_key' => $log->action,
                'subject' => $log->subject_type ? (self::SUBJECTS[$log->subject_type] ?? class_basename($log->subject_type)) : null,
                'subject_id' => $log->subject_id,
                'details' => $this->details($log),
                'ip' => $log->ip,
            ]);

        return Inertia::render('audit/Index', [
            'logs' => $logs,
            'filters' => ['action' => $action, 'user' => $userId],
            'actions' => collect(self::ACTIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'users' => User::query()->where('tenant_id', $currentTenant->id())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Kısa, okunur özet. Hassas alanların değeri zaten loglanmaz ("[gizli]").
     */
    private function details(AuditLog $log): ?string
    {
        $changes = $log->changes ?? [];

        return match ($log->action) {
            'export' => trim(($changes['report'] ?? '').' · '.strtoupper((string) ($changes['format'] ?? '')).' · '.($changes['rows'] ?? '?').' satır', ' ·'),
            'login_failed' => $changes['email'] ?? null,
            'view_sensitive' => 'Kimlik / pasaport no',
            'update' => implode(', ', array_keys($changes)) ?: null,
            default => null,
        };
    }
}
