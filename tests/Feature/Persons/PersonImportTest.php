<?php

namespace Tests\Feature\Persons;

use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Rules\TcKimlikNo;
use App\Support\Imports\PersonRowNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Excel'den toplu yolcu aktarma: önizleme, onay, tur kaydı, güvenlik.
 */
class PersonImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
    }

    public function test_template_downloads(): void
    {
        $this->actingAs($this->staff)->get(route('person-import.template'))
            ->assertOk()
            ->assertDownload('marhal-yolcu-aktarma-sablonu.xlsx');
    }

    public function test_preview_classifies_rows_and_masks_identity_numbers(): void
    {
        $existingTc = TcKimlikNo::generate();
        Person::factory()->create(['tenant_id' => $this->tenant->id, 'national_id' => $existingTc]);
        $newTc = TcKimlikNo::generate();

        $token = $this->upload([
            ['Ali', 'Kaya', 'E', '15.03.1965', $newTc, '5321234567'],
            ['Veli', 'Demir', 'Erkek', '01.01.1970', $existingTc, ''],
            ['Ayşe', 'Ak', '', '01.01.1970', '', ''],                 // cinsiyet yok
            ['Zeynep', 'Su', 'Kadın', '01.01.1970', '12345678901', ''], // geçersiz T.C.
            ['Ali', 'Kaya', 'E', '15.03.1965', $newTc, ''],           // dosyada tekrar
            ['', '', '', '', '', ''],                                  // boş satır atlanır
        ]);

        $this->actingAs($this->staff)->get(route('person-import.show', ['token' => $token]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('persons/Import')
                ->has('preview.rows', 5)
                ->where('preview.rows.0.status', 'yeni')
                ->where('preview.rows.0.summary.phone', '05321234567')
                ->where('preview.rows.0.summary.birth_date', '1965-03-15')
                ->where('preview.rows.0.summary.national_id', '*******'.substr($newTc, -4))
                ->missing('preview.rows.0.data')
                ->where('preview.rows.1.status', 'mevcut')
                ->where('preview.rows.2.status', 'hata')
                ->where('preview.rows.3.status', 'hata')
                ->where('preview.rows.4.status', 'hata')
                ->where('preview.rows.4.errors.0', 'T.C. Kimlik No dosyada 2. satırda da var'));

        $this->assertSame(1, Person::count(), 'Önizleme hiçbir şey kaydetmez');
    }

    public function test_confirm_creates_new_people_and_registers_them_to_a_tour(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'USD', 'default_price' => 1200, 'capacity' => 45]);
        $group = Group::factory()->create(['tour_id' => $tour->id]);
        $existing = Person::factory()->create(['tenant_id' => $this->tenant->id, 'passport_no' => 'U11112222']);

        $token = $this->upload([
            ['Ali', 'Kaya', 'E', '15.03.1965', '', '', 'U55556666', '2', '1500', 'Evet'],
            ['Veli', 'Demir', 'Erkek', '01.01.1970', '', '', 'u1111 2222', '', '', ''],
            ['Hatalı', 'Satır', 'X', '', '', '', '', '', '', ''],
        ], extraHeaders: ['Pasaport No', 'Oda Tipi', 'Ücret', 'KVKK Onayı']);

        $this->actingAs($this->staff)->post(route('person-import.store'), [
            'token' => $token, 'tour_id' => $tour->id, 'group_id' => $group->id, 'status' => 'kesin_kayit',
        ])->assertRedirect(route('tours.show', $tour));

        $ali = Person::query()->wherePassportNo('U55556666')->sole();
        $this->assertSame(Gender::Male, $ali->gender);
        $this->assertNotNull($ali->kvkk_consent_at);
        $this->assertSame(2, Person::count(), 'Mevcut kişi tekrar oluşturulmaz, hatalı satır atlanır');

        $aliReg = Registration::where('person_id', $ali->id)->sole();
        $this->assertSame(['2li', '1500.00', RegistrationStatus::Confirmed, $group->id], [$aliReg->room_type?->value, $aliReg->price, $aliReg->status, $aliReg->group_id]);
        $this->assertSame('1200.00', Registration::where('person_id', $existing->id)->sole()->price, 'Ücret boşsa turun fiyatı');

        $this->assertDatabaseHas('audit_logs', ['action' => 'import', 'tenant_id' => $this->tenant->id]);

        // Aynı önizleme ikinci kez kullanılamaz.
        $this->actingAs($this->staff)->post(route('person-import.store'), ['token' => $token])->assertSessionHasErrors('token');
    }

    public function test_capacity_failures_are_reported_but_people_are_still_created(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 1]);

        $token = $this->upload([
            ['Ali', 'Kaya', 'E', '', '', ''],
            ['Veli', 'Demir', 'E', '', '', ''],
        ]);

        $this->actingAs($this->staff)->post(route('person-import.store'), ['token' => $token, 'tour_id' => $tour->id]);

        $this->assertSame(2, Person::count());
        $this->assertSame(1, $tour->registrations()->count());
    }

    public function test_tokens_are_private_and_access_is_restricted(): void
    {
        $token = $this->upload([['Ali', 'Kaya', 'E', '', '', '']]);

        // Başka kullanıcı (aynı acente) jetonu kullanamaz.
        $colleague = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();
        $this->actingAs($colleague)->post(route('person-import.store'), ['token' => $token])->assertSessionHasErrors('token');

        // Başka acentenin turuna kayıt istenemez.
        $foreignTour = Tour::factory()->create();
        $this->actingAs($this->staff)->post(route('person-import.store'), ['token' => $token, 'tour_id' => $foreignTour->id])
            ->assertSessionHasErrors('tour_id');

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->get(route('person-import.show'))->assertForbidden();
        $this->actingAs($guide)->get(route('person-import.template'))->assertForbidden();
    }

    public function test_same_number_in_another_agency_does_not_count_as_existing(): void
    {
        $tc = TcKimlikNo::generate();
        Person::factory()->create(['national_id' => $tc]); // başka acente

        $token = $this->upload([['Ali', 'Kaya', 'E', '', $tc, '']]);

        $this->actingAs($this->staff)->get(route('person-import.show', ['token' => $token]))
            ->assertInertia(fn (Assert $page) => $page->where('preview.rows.0.status', 'yeni'));
    }

    public function test_missing_required_columns_are_reported(): void
    {
        $file = UploadedFile::fake()->createWithContent('liste.csv', "Adı,Telefon\nAli,0532\n");

        $this->actingAs($this->staff)->post(route('person-import.upload'), ['file' => $file])
            ->assertSessionHasErrors(['file' => 'Zorunlu sütun bulunamadı: Soyad, Cinsiyet. Lütfen şablondaki başlıkları kullanın.']);
    }

    public function test_normalizer_understands_common_spellings(): void
    {
        $map = PersonRowNormalizer::mapHeaders(['ADI', 'Soyadı', 'Cinsiyeti', 'T.C. Kimlik No', 'Doğum Tarihi', 'GSM', 'Oda']);
        $this->assertSame(['first_name', 'last_name', 'gender', 'national_id', 'birth_date', 'phone', 'room_type'], array_values($map));

        $row = PersonRowNormalizer::row(['Ali', 'Kaya', 'Bay', 12345678950.0, 25569.0, 5321234567, '4 kişilik'], $map);

        $this->assertSame('erkek', $row['gender']);
        $this->assertSame('12345678950', $row['national_id']);
        $this->assertSame('1970-01-01', $row['birth_date']); // Excel tarih sayısı 25569 = 01.01.1970
        $this->assertSame('05321234567', $row['phone']);
        $this->assertSame('4lu', $row['room_type']);
    }

    /**
     * @param  list<list<string>>  $rows  Ad, Soyad, Cinsiyet, Doğum Tarihi, T.C., Telefon [+ ekler]
     * @param  list<string>  $extraHeaders
     */
    private function upload(array $rows, array $extraHeaders = []): string
    {
        $headers = ['Ad', 'Soyad', 'Cinsiyet', 'Doğum Tarihi', 'T.C. Kimlik No', 'Telefon', ...$extraHeaders];
        $csv = collect([$headers, ...$rows])
            ->map(fn (array $r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r)))
            ->join("\n");

        $response = $this->actingAs($this->staff)->post(route('person-import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('yolcular.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        return $this->tokenFrom($response);
    }

    private function tokenFrom(TestResponse $response): string
    {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return (string) ($query['token'] ?? '');
    }
}
