<?php

namespace App\Reports;

use App\Models\Tenant;
use App\Reports\Exporters\XlsxExport;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\CurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raporu istenen biçimde indirtir ve her dışa aktarımı audit log'a yazar (SPEC.md §7).
 */
class ReportResponder
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly AuditLogger $audit,
    ) {}

    public function download(Report $report, string $format): Response
    {
        $this->audit->log('export', changes: [
            'report' => $report->key,
            'format' => $format,
            'rows' => count($report->rows),
        ]);

        $tenant = $this->currentTenant->get();
        $header = $this->header($tenant);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.table', [
                'report' => $report,
                'tenant' => $tenant,
                'header' => $header,
                'logo' => $this->logoDataUri($tenant),
                'generatedAt' => now(),
                'formatValue' => $this->formatter(),
            ])
                ->setPaper('a4', $report->landscape ? 'landscape' : 'portrait')
                // Yazı tipinin sadece kullanılan karakterleri gömülür (dosya ~800 KB → ~30 KB).
                ->setOption(['isFontSubsettingEnabled' => true, 'isPhpEnabled' => false, 'isRemoteEnabled' => false])
                ->download($report->filename('pdf'));
        }

        return Excel::download(new XlsxExport($report, $header), $report->filename('xlsx'));
    }

    /**
     * Tablo olmayan özel PDF'ler (ör. otobüs koltuk planı çizimi). Aynı başlık / logo / audit kuralları.
     *
     * @param  array<string, mixed>  $data
     */
    public function pdfView(string $key, string $filename, string $view, array $data, bool $landscape = false): Response
    {
        $this->audit->log('export', changes: ['report' => $key, 'format' => 'pdf']);

        $tenant = $this->currentTenant->get();

        return Pdf::loadView($view, [
            ...$data,
            'tenant' => $tenant,
            'logo' => $this->logoDataUri($tenant),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption(['isFontSubsettingEnabled' => true, 'isPhpEnabled' => false, 'isRemoteEnabled' => false])
            ->download($filename);
    }

    /**
     * Logo PDF'e gömülü (data URI) olarak verilir; dompdf'te uzak/yerel dosya erişimi kapalı kalır.
     */
    private function logoDataUri(?Tenant $tenant): ?string
    {
        $disk = Storage::disk(config('marhal.media_disk'));

        if (! $tenant?->logo_path || ! $disk->exists($tenant->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($tenant->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($tenant->logo_path));
    }

    /**
     * @return list<string>
     */
    private function header(?Tenant $tenant): array
    {
        return array_values(array_filter([
            $tenant?->name,
            $tenant?->phone,
            'Oluşturma: '.now()->format('d.m.Y H:i'),
        ]));
    }

    /**
     * PDF hücreleri için biçimlendirici (para: 1.234,50 · tarih: 25.10.2026).
     *
     * @return callable(Column, mixed): string
     */
    private function formatter(): callable
    {
        return function (Column $column, mixed $value): string {
            if ($value === null || $value === '') {
                return '';
            }

            return match ($column->type) {
                'money' => is_numeric($value) ? number_format((float) $value, 2, ',', '.') : (string) $value,
                'date' => CarbonImmutable::parse((string) $value)->format('d.m.Y'),
                default => (string) $value,
            };
        };
    }
}
