<?php

namespace App\Actions\Persons;

use App\Actions\Registrations\RegisterPerson;
use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Http\Requests\PersonRequest;
use App\Models\Group;
use App\Models\Person;
use App\Models\Tour;
use App\Support\Imports\PersonRowNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Excel'den toplu yolcu aktarma. Önce önizleme (hiçbir şey kaydedilmez), onaylanınca geçerli satırlar
 * kaydedilir. Elle kayıtla aynı kurallar (PersonRequest::fieldRules) ve aynı tur kaydı (RegisterPerson).
 *
 * Satır durumları:
 *  - yeni: kişi oluşturulacak
 *  - mevcut: T.C. veya pasaport no ile zaten kayıtlı; kişi değiştirilmez (istenirse tura kaydedilir)
 *  - hata: aktarılmaz; nedenleri gösterilir
 */
class ImportPersons
{
    public const MAX_ROWS = 1000;

    public function __construct(private readonly RegisterPerson $registerPerson) {}

    /**
     * @param  array<int, array<int, mixed>>  $sheet  ilk satır başlık
     * @return array{rows: list<array<string, mixed>>, unknown_headers: list<string>, missing_headers: list<string>}
     */
    public function preview(array $sheet): array
    {
        $headers = array_shift($sheet) ?? [];
        $map = PersonRowNormalizer::mapHeaders($headers);

        $missing = array_values(array_diff(['first_name', 'last_name', 'gender'], $map));
        if ($missing !== []) {
            $labels = ['first_name' => 'Ad', 'last_name' => 'Soyad', 'gender' => 'Cinsiyet'];

            throw ValidationException::withMessages([
                'file' => 'Zorunlu sütun bulunamadı: '.implode(', ', array_map(fn ($f) => $labels[$f], $missing))
                    .'. Lütfen şablondaki başlıkları kullanın.',
            ]);
        }

        $unknown = collect($headers)
            ->filter(fn ($h, $i) => ! isset($map[$i]) && trim((string) $h) !== '')
            ->map(fn ($h) => (string) $h)
            ->values()
            ->all();

        $rows = [];
        $seenIds = [];

        foreach ($sheet as $offset => $raw) {
            $data = PersonRowNormalizer::row($raw, $map);

            if ($data === null) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'Bir seferde en fazla '.self::MAX_ROWS.' yolcu aktarılabilir. Dosyayı bölün.']);
            }

            $rows[] = $this->check($data, $offset + 2, $seenIds);
        }

        return ['rows' => $rows, 'unknown_headers' => $unknown, 'missing_headers' => []];
    }

    /**
     * Onaylanan önizlemeyi kaydeder. Hatalı satırlar atlanır; tur kaydı istenirse (kapasite vb.)
     * kaydedilemeyenler raporlanır, kişiler yine de oluşturulur.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array{tour?: Tour|null, group?: Group|null, status?: string, price?: string|float|int|null}  $registration
     * @return array{created: int, existing: int, skipped: int, registered: int, failures: list<string>}
     */
    public function commit(array $rows, array $registration = []): array
    {
        $result = ['created' => 0, 'existing' => 0, 'skipped' => 0, 'registered' => 0, 'failures' => []];
        $tour = $registration['tour'] ?? null;

        foreach ($rows as $row) {
            if ($row['status'] === 'hata') {
                $result['skipped']++;

                continue;
            }

            /** @var array<string, mixed> $data */
            $data = $row['data'];

            $person = DB::transaction(function () use ($row, $data, &$result): Person {
                if ($row['status'] === 'mevcut') {
                    $result['existing']++;

                    return Person::query()->whereKey($row['person_id'])->firstOrFail();
                }

                // Önizlemeden sonra başka biri aynı kişiyi eklemiş olabilir: tekrar kontrol.
                $existing = $this->findExisting($data);
                if ($existing !== null) {
                    $result['existing']++;

                    return $existing;
                }

                $person = new Person(collect($data)->only(array_keys(PersonRequest::fieldRules('TR')))->all());
                $person->kvkk_consent_at = $data['kvkk'] ? now() : null;
                $person->save();
                $result['created']++;

                return $person;
            });

            if ($tour === null || $tour->registrations()->where('person_id', $person->id)->exists()) {
                continue;
            }

            try {
                $this->registerPerson->handle($tour, [
                    'person_id' => $person->id,
                    'group_id' => $registration['group']?->id ?? null,
                    'room_type' => $data['room_type'] ?? null,
                    'price' => $data['price'] ?? $registration['price'] ?? $tour->default_price ?? 0,
                    'status' => $registration['status'] ?? RegistrationStatus::Pending->value,
                ]);
                $result['registered']++;
            } catch (ValidationException $e) {
                $result['failures'][] = "{$person->full_name}: ".collect($e->errors())->flatten()->first();
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seenIds  dosya içi tekrar kontrolü (T.C. / pasaport → satır no)
     * @return array<string, mixed>
     */
    private function check(array $data, int $line, array &$seenIds): array
    {
        $errors = [];
        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: '(adsız)';

        foreach (['national_id' => 'T.C. Kimlik No', 'passport_no' => 'Pasaport no'] as $field => $label) {
            $value = $data[$field] ?? null;

            if ($value !== null) {
                $key = "{$field}:{$value}";

                if (isset($seenIds[$key])) {
                    $errors[] = "{$label} dosyada {$seenIds[$key]}. satırda da var";
                }

                $seenIds[$key] ??= $line;
            }
        }

        $existing = $this->findExisting($data);

        if ($existing === null && $this->matchesDeleted($data)) {
            $errors[] = 'Bu T.C. / pasaport no ile silinmiş bir kayıt var; önce yolcular listesinden kontrol edin';
        }

        // Mevcut kişi değiştirilmez; sadece tur kaydı bilgileri kontrol edilir.
        $rules = $existing ? [] : PersonRequest::fieldRules($data['nationality'] ?? null);
        $rules['room_type'] = ['nullable', Rule::enum(RoomType::class)];
        $rules['price'] = ['nullable', 'numeric', 'min:0', 'max:9999999'];

        $validator = Validator::make($data, $rules, [
            'room_type.enum' => 'Oda tipi 2, 3, 4 veya 5 olmalı.',
            'gender.enum' => 'Cinsiyet "Erkek" veya "Kadın" olmalı.',
        ], [...PersonRequest::fieldAttributes(), 'room_type' => 'oda tipi', 'price' => 'ücret']);

        $errors = [...$errors, ...$validator->errors()->all()];

        return [
            'line' => $line,
            'name' => $name,
            'status' => $errors !== [] ? 'hata' : ($existing ? 'mevcut' : 'yeni'),
            'errors' => $errors,
            'person_id' => $existing?->id,
            // Önizleme ekranında gösterilecek özet (kimlik / pasaport maskeli).
            'summary' => [
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'national_id' => self::mask($data['national_id'] ?? null),
                'passport_no' => self::mask($data['passport_no'] ?? null),
                'phone' => $data['phone'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'price' => $data['price'] ?? null,
            ],
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findExisting(array $data): ?Person
    {
        if (! empty($data['national_id']) && is_string($data['national_id'])) {
            $person = Person::query()->whereNationalId($data['national_id'])->first();

            if ($person) {
                return $person;
            }
        }

        if (! empty($data['passport_no']) && is_string($data['passport_no'])) {
            return Person::query()->wherePassportNo($data['passport_no'])->first();
        }

        return null;
    }

    /**
     * Silinmiş (çöp kutusundaki) bir kişiyle aynı numara: veritabanı tekillik kuralı yeni kayda izin vermez.
     *
     * @param  array<string, mixed>  $data
     */
    private function matchesDeleted(array $data): bool
    {
        return (is_string($data['national_id'] ?? null) && $data['national_id'] !== ''
                && Person::onlyTrashed()->whereNationalId($data['national_id'])->exists())
            || (is_string($data['passport_no'] ?? null) && $data['passport_no'] !== ''
                && Person::onlyTrashed()->wherePassportNo($data['passport_no'])->exists());
    }

    private static function mask(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
