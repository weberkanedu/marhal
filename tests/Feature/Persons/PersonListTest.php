<?php

namespace Tests\Feature\Persons;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsSpreadsheets;
use Tests\TestCase;

/**
 * Yolcular ekranı: sayı kartları, hazır süzgeçler, maskeli liste, "Çıktı al" listeleri.
 */
class PersonListTest extends TestCase
{
    use ReadsSpreadsheets, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
    }

    public function test_passport_issue_rule(): void
    {
        $valid = $this->person(['passport_no' => 'U1234567', 'passport_expiry_date' => now()->addYears(2)]);
        $expiring = $this->person(['passport_no' => 'U7654321', 'passport_expiry_date' => now()->addMonths(3)]);
        $noNumber = $this->person(['passport_no' => null, 'passport_expiry_date' => now()->addYears(2)]);
        $noDate = $this->person(['passport_no' => 'U1111111', 'passport_expiry_date' => null]);

        $this->assertNull($valid->passportIssue());
        $this->assertSame('6 aydan az geçerli', $expiring->passportIssue());
        $this->assertSame('Pasaport no yok', $noNumber->passportIssue());
        $this->assertSame('Bitiş tarihi yok', $noDate->passportIssue());

        // Sorgu hâli aynı kişileri bulur.
        $this->assertEqualsCanonicalizing(
            [$expiring->id, $noNumber->id, $noDate->id],
            Person::query()->withPassportIssue()->pluck('id')->all(),
        );
    }

    public function test_list_shows_stats_filters_and_masks_phone(): void
    {
        $onTour = $this->person(['first_name' => 'Ali', 'phone' => '05321234567', 'passport_no' => 'U1234567', 'passport_expiry_date' => now()->addYears(2), 'kvkk_consent_at' => now()]);
        $this->person(['first_name' => 'Veli', 'passport_no' => null, 'kvkk_consent_at' => null]);

        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'status' => TourStatus::OnSale, 'end_date' => now()->addMonth()]);
        Registration::factory()->create(['tour_id' => $tour->id, 'person_id' => $onTour->id, 'status' => RegistrationStatus::Confirmed]);

        $this->actingAs($this->staff)->get(route('persons.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 2)
                ->where('stats.pasaport', 1)
                ->where('stats.turda', 1)
                ->where('stats.kvkk', 1)
                ->has('filterOptions', 3)
                ->where('persons.data.0.full_name', 'Ali Yılmaz')
                ->where('persons.data.0.masked_phone', '0532 *** ** 67')
                ->where('persons.data.0.on_tour', true)
                ->missing('persons.data.0.phone'));

        $this->actingAs($this->staff)->get(route('persons.index', ['filtre' => 'pasaport']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('persons.data', 1)
                ->where('persons.data.0.passport_issue', 'Pasaport no yok')
                ->where('filters.filtre', 'pasaport'));
    }

    public function test_person_reports_download_with_filter_and_only_own_agency(): void
    {
        $this->person(['first_name' => 'Ali', 'passport_no' => null]);
        Person::factory()->create(['first_name' => 'Yabancı']); // başka acente

        $list = $this->sheetText($this->actingAs($this->staff)->get(route('reports.persons.list'))->assertOk());
        $this->assertStringContainsString('Ali', $list);
        $this->assertStringNotContainsString('Yabancı', $list);

        $passports = $this->sheetText($this->actingAs($this->staff)->get(route('reports.persons.passports', ['filtre' => 'pasaport'])));
        $this->assertStringContainsString('Pasaport Kontrol Listesi', $passports);
        $this->assertStringContainsString('Pasaport no yok', $passports);

        $this->actingAs($this->staff)->get(route('reports.persons.passports', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->get(route('reports.persons.list'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function person(array $attributes): Person
    {
        return Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'last_name' => 'Yılmaz',
            ...$attributes,
        ]);
    }
}
