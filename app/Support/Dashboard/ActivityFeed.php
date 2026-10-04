<?php

namespace App\Support\Dashboard;

use App\Enums\PaymentType;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Registration;
use Illuminate\Support\Collection;

/**
 * Ana paneldeki "Son hareketler": erişim kayıtlarından (audit_logs) okunur, insan diliyle yazılır.
 * Sadece iş açısından anlamlı olaylar: yeni yolcu, tura kayıt, kayıt iptali, tahsilat / iade.
 * Kimlik / pasaport gibi hassas alanlar zaten audit kaydına yazılmaz.
 */
class ActivityFeed
{
    private const LIMIT = 8;

    /**
     * @return array<int, array{id: int, text: string, user: string|null, at: string, tone: string}>
     */
    public function latest(string $tenantId): array
    {
        // audit_logs acente kapsamı (global scope) kullanmaz: acente burada açıkça filtrelenir.
        $logs = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('subject_type', [Person::class, Registration::class, Payment::class])->where('action', 'create'))
                ->orWhere(fn ($w) => $w->where('subject_type', Registration::class)->where('action', 'update')))
            ->with('user:id,name')
            ->latest('id')
            ->limit(self::LIMIT * 4) // güncellemelerin çoğu (ör. not değişikliği) akışa girmez
            ->get();

        $registrations = Registration::withTrashed()
            ->with(['person' => fn ($q) => $q->withTrashed(), 'tour:id,name'])
            ->whereIn('id', collect($this->ids($logs, Registration::class))
                ->merge(Payment::withTrashed()->whereIn('id', $this->ids($logs, Payment::class))->pluck('registration_id')))
            ->get()
            ->keyBy('id');
        $payments = Payment::withTrashed()->whereIn('id', $this->ids($logs, Payment::class))->get()->keyBy('id');
        $persons = Person::withTrashed()->whereIn('id', $this->ids($logs, Person::class))->get()->keyBy('id');

        return $logs
            ->map(fn (AuditLog $log) => $this->describe($log, $registrations, $payments, $persons))
            ->filter()
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, Registration>  $registrations
     * @param  Collection<string, Payment>  $payments
     * @param  Collection<string, Person>  $persons
     * @return array{id: int, text: string, user: string|null, at: string, tone: string}|null
     */
    private function describe(AuditLog $log, Collection $registrations, Collection $payments, Collection $persons): ?array
    {
        $text = null;
        $tone = 'neutral';

        if ($log->subject_type === Person::class) {
            $person = $persons->get((string) $log->subject_id);
            $text = $person ? "Yeni yolcu kaydı: {$person->full_name}" : null;
        } elseif ($log->subject_type === Registration::class) {
            $registration = $registrations->get((string) $log->subject_id);
            $name = $registration?->person->full_name;

            if ($registration && $log->action === 'create') {
                $text = "{$name}, {$registration->tour->name} turuna eklendi";
            } elseif ($registration && ($log->changes['status'] ?? null) === 'iptal') {
                $text = "{$name} kaydı iptal edildi";
                $tone = 'danger';
            }
        } elseif ($log->subject_type === Payment::class) {
            $payment = $payments->get((string) $log->subject_id);
            $registration = $payment ? $registrations->get($payment->registration_id) : null;

            if ($payment && $registration) {
                $amount = number_format((float) $payment->amount, 2, ',', '.').' '.$payment->currency;
                $text = $payment->type === PaymentType::Refund
                    ? "{$registration->person->full_name} için {$amount} iade"
                    : "{$registration->person->full_name} için {$amount} tahsilat";
                $tone = $payment->type === PaymentType::Refund ? 'warning' : 'success';
            }
        }

        if ($text === null) {
            return null;
        }

        return [
            'id' => $log->id,
            'text' => $text,
            'user' => $log->user?->name,
            'at' => $log->created_at->toIso8601String(),
            'tone' => $tone,
        ];
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<int, string>
     */
    private function ids(Collection $logs, string $type): array
    {
        return $logs->where('subject_type', $type)->pluck('subject_id')->filter()->map(fn ($id) => (string) $id)->values()->all();
    }
}
