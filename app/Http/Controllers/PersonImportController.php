<?php

namespace App\Http\Controllers;

use App\Actions\Persons\ImportPersons;
use App\Enums\RegistrationStatus;
use App\Models\Group;
use App\Models\Person;
use App\Models\Tour;
use App\Reports\Exporters\PersonImportTemplate;
use App\Support\Audit\AuditLogger;
use App\Support\Imports\FirstSheetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Excel'den toplu yolcu aktarma: şablon indir → dosya yükle → önizleme → onayla.
 *
 * Önizleme verisi (kimlik / pasaport no içerir) dosyaya yazılmaz; şifrelenip 30 dakikalığına
 * önbellekte, kullanıcıya özel bir anahtarla tutulur ve aktarımdan sonra silinir.
 */
class PersonImportController extends Controller
{
    private const TTL_MINUTES = 30;

    public function create(Request $request): Response
    {
        Gate::authorize('create', Person::class);

        $token = (string) $request->query('token', '');
        $preview = $token !== '' ? $this->load($request, $token) : null;

        return Inertia::render('persons/Import', [
            'token' => $preview ? $token : null,
            'preview' => $preview ? [
                // Ekrana yalnız özet gider (kimlik / pasaport maskeli); tam veri sunucuda kalır.
                'rows' => collect($preview['rows'])->map(fn (array $row) => collect($row)->except('data')->all())->values(),
                'unknown_headers' => $preview['unknown_headers'],
                'file_name' => $preview['file_name'],
            ] : null,
            'expired' => $token !== '' && $preview === null,
            'tours' => Tour::query()->active()->orderBy('start_date')->with('groups:id,tour_id,name')
                ->get(['id', 'name', 'start_date', 'default_price', 'currency', 'capacity'])
                ->map(fn (Tour $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'default_price' => $t->default_price,
                    'currency' => $t->currency,
                    'groups' => $t->groups->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])->values(),
                ]),
            'statuses' => collect(RegistrationStatus::options())->reject(fn (array $o) => $o['value'] === RegistrationStatus::Cancelled->value)->values(),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        Gate::authorize('create', Person::class);

        return Excel::download(new PersonImportTemplate, 'marhal-yolcu-aktarma-sablonu.xlsx');
    }

    public function upload(Request $request, ImportPersons $import): RedirectResponse
    {
        Gate::authorize('create', Person::class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt'],
        ], ['file.mimes' => 'Excel (.xlsx, .xls) veya CSV dosyası yükleyin.'], ['file' => 'dosya']);

        $sheet = new FirstSheetImport;

        try {
            Excel::import($sheet, $request->file('file'));
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Dosya okunamadı. Excel (.xlsx) olarak kaydedip tekrar deneyin.']);
        }

        $preview = $import->preview($sheet->rows);

        if ($preview['rows'] === []) {
            throw ValidationException::withMessages(['file' => 'Dosyada aktarılacak satır bulunamadı.']);
        }

        $token = Str::random(32);
        Cache::put($this->cacheKey($request, $token), Crypt::encryptString((string) json_encode([
            ...$preview,
            'file_name' => $request->file('file')?->getClientOriginalName(),
        ])), now()->addMinutes(self::TTL_MINUTES));

        return to_route('person-import.show', ['token' => $token]);
    }

    public function store(Request $request, ImportPersons $import, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', Person::class);

        $data = $request->validate([
            'token' => ['required', 'string', 'size:32'],
            'tour_id' => ['nullable', 'uuid', Rule::exists('tours', 'id')->where('tenant_id', $request->user()?->tenant_id)->whereNull('deleted_at')],
            'group_id' => ['nullable', 'uuid', Rule::exists('groups', 'id')->where('tour_id', $request->input('tour_id'))->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in([RegistrationStatus::Pending->value, RegistrationStatus::Confirmed->value])],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ], [], ['tour_id' => 'tur', 'group_id' => 'grup', 'price' => 'ücret']);

        $preview = $this->load($request, $data['token']);

        if ($preview === null) {
            throw ValidationException::withMessages(['token' => 'Önizlemenin süresi doldu. Dosyayı tekrar yükleyin.']);
        }

        $tour = isset($data['tour_id']) ? Tour::query()->whereKey($data['tour_id'])->firstOrFail() : null;

        $result = $import->commit($preview['rows'], [
            'tour' => $tour,
            'group' => $tour && isset($data['group_id']) ? $tour->groups()->whereKey($data['group_id'])->first() : null,
            'status' => $data['status'] ?? RegistrationStatus::Pending->value,
            'price' => $data['price'] ?? null,
        ]);

        Cache::forget($this->cacheKey($request, $data['token']));
        $audit->log('import', changes: [
            'file' => $preview['file_name'],
            'rows' => count($preview['rows']),
            ...collect($result)->except('failures')->all(),
            'tour' => $tour?->name,
        ]);

        $message = "{$result['created']} yeni yolcu eklendi";
        $message .= $result['existing'] ? ", {$result['existing']} kişi zaten kayıtlıydı" : '';
        $message .= $result['skipped'] ? ", {$result['skipped']} hatalı satır atlandı" : '';
        $message .= $tour ? ", {$result['registered']} kişi {$tour->name} turuna kaydedildi" : '';
        $message .= '.';
        if ($result['failures'] !== []) {
            $message .= ' Tura kaydedilemeyenler: '.implode('; ', array_slice($result['failures'], 0, 5)).(count($result['failures']) > 5 ? ' …' : '');
        }

        Inertia::flash('toast', ['type' => $result['failures'] === [] && $result['skipped'] === 0 ? 'success' : 'warning', 'message' => $message]);

        return $tour ? to_route('tours.show', $tour) : to_route('persons.index');
    }

    /**
     * @return array{rows: list<array<string, mixed>>, unknown_headers: list<string>, file_name: string|null}|null
     */
    private function load(Request $request, string $token): ?array
    {
        $payload = Cache::get($this->cacheKey($request, $token));

        if (! is_string($payload)) {
            return null;
        }

        try {
            /** @var array{rows: list<array<string, mixed>>, unknown_headers: list<string>, file_name: string|null} $data */
            $data = json_decode(Crypt::decryptString($payload), true, flags: JSON_THROW_ON_ERROR);

            return $data;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Anahtar kullanıcıya ve acenteye bağlı: başka biri aynı jetonu kullanamaz.
     */
    private function cacheKey(Request $request, string $token): string
    {
        return 'person-import:'.$request->user()?->tenant_id.':'.$request->user()?->getKey().':'.hash('sha256', $token);
    }
}
