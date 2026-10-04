<?php

namespace Tests\Feature\Reports;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tour $tour;

    private Group $groupA;

    private Person $ayse;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Payments, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id, 'name' => 'Rapor Turizm']);
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kasım Umresi', 'currency' => 'USD']);
        $this->groupA = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
        $groupB = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B Grubu']);

        $this->ayse = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ayşe', 'last_name' => 'Şahin', 'passport_no' => 'U1234567']);
        $mehmet = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Mehmet', 'last_name' => 'Öz']);
        $cancelled = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'İptal', 'last_name' => 'Kişi']);

        $r1 = Registration::factory()->create(['tour_id' => $this->tour->id, 'group_id' => $this->groupA->id, 'person_id' => $this->ayse->id, 'price' => 1500]);
        Registration::factory()->create(['tour_id' => $this->tour->id, 'group_id' => $groupB->id, 'person_id' => $mehmet->id, 'price' => 1500]);
        Registration::factory()->create(['tour_id' => $this->tour->id, 'person_id' => $cancelled->id, 'status' => RegistrationStatus::Cancelled]);
        Payment::factory()->create(['registration_id' => $r1->id, 'amount' => 1000]);
    }

    public function test_passenger_list_excel_contains_active_passengers_with_masked_ids_for_staff(): void
    {
        $response = $this->actingAs($this->user(UserRole::Operations))
            ->get(route('reports.tours.passengers', [$this->tour, 'format' => 'xlsx']))
            ->assertOk();

        $sheet = $this->sheet($response);
        $text = $this->sheetText($sheet);

        $this->assertSame('Kasım Umresi Yolcu Listesi', $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('ŞAHİN', $text);
        $this->assertStringContainsString('ÖZ', $text);
        $this->assertStringNotContainsString('İptal', $text, 'İptal edilen kayıt listede olmamalı.');
        $this->assertStringNotContainsString('U1234567', $text);
        $this->assertStringContainsString('*****567', $text);
        $this->assertStringNotContainsString($this->ayse->national_id, $text);
    }

    public function test_admin_gets_full_ids_in_passenger_list(): void
    {
        $response = $this->actingAs($this->user(UserRole::Admin))
            ->get(route('reports.tours.passengers', [$this->tour, 'format' => 'xlsx']));

        $text = $this->sheetText($this->sheet($response));
        $this->assertStringContainsString('U1234567', $text);
        $this->assertStringContainsString($this->ayse->national_id, $text);
    }

    public function test_passenger_list_can_be_limited_to_a_group(): void
    {
        $response = $this->actingAs($this->user(UserRole::Operations))
            ->get(route('reports.tours.passengers', [$this->tour, 'group' => $this->groupA->id, 'format' => 'xlsx']));

        $text = $this->sheetText($this->sheet($response));
        $this->assertStringContainsString('ŞAHİN', $text);
        $this->assertStringNotContainsString('ÖZ', $text);
    }

    public function test_payment_status_report_has_correct_totals(): void
    {
        $response = $this->actingAs($this->user(UserRole::Operations))
            ->get(route('reports.tours.payments', [$this->tour, 'format' => 'xlsx']));

        $rows = $this->sheet($response)->toArray(null, true, false);
        $totalRow = collect($rows)->first(fn (array $row) => $row[0] === 'TOPLAM');

        $this->assertNotNull($totalRow, 'Toplam satırı olmalı.');
        $this->assertEquals([3000.0, 1000.0, 2000.0], array_slice($totalRow, 4, 3), 'Net, ödenen, kalan toplamları');
    }

    public function test_pdf_reports_are_generated(): void
    {
        $user = $this->user(UserRole::Operations);

        foreach ([
            route('reports.tours.passengers', [$this->tour, 'format' => 'pdf']),
            route('reports.tours.payments', [$this->tour, 'format' => 'pdf']),
            route('reports.collections', ['tab' => 'borclu', 'format' => 'pdf']),
            route('reports.collections', ['tab' => 'tahsilatlar', 'format' => 'pdf']),
        ] as $url) {
            $response = $this->actingAs($user)->get($url)->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        }
    }

    public function test_collections_report_matches_the_screen(): void
    {
        $response = $this->actingAs($this->user(UserRole::Operations))
            ->get(route('reports.collections', ['tab' => 'borclu', 'tour' => $this->tour->id, 'format' => 'xlsx']));

        $text = $this->sheetText($this->sheet($response));
        $this->assertStringContainsString('Borçlu Yolcular', $text);
        $this->assertStringContainsString('Ayşe Şahin', $text);
        $this->assertStringContainsString('Mehmet Öz', $text);
    }

    public function test_exports_are_written_to_the_audit_log(): void
    {
        $user = $this->user(UserRole::Operations);
        $this->actingAs($user)->get(route('reports.tours.passengers', [$this->tour, 'format' => 'pdf']));

        $log = AuditLog::where('action', 'export')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('tour_passengers', $log->changes['report']);
        $this->assertSame(2, $log->changes['rows']);
    }

    public function test_reports_are_blocked_without_feature_and_for_other_tenants(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $noReports = User::factory()->forTenant(Tenant::factory()->create(['plan_id' => $plan->id]))->create();
        $this->actingAs($noReports)->get(route('reports.collections'))->assertForbidden();

        $otherPlan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BasicReports])->create();
        $other = User::factory()->forTenant(Tenant::factory()->create(['plan_id' => $otherPlan->id]))->create();
        $this->actingAs($other)->get(route('reports.tours.passengers', $this->tour))->assertNotFound();
    }

    private function user(UserRole $role): User
    {
        return User::factory()->forTenant($this->tenant)->role($role)->create();
    }

    private function sheet(TestResponse $response): Worksheet
    {
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);

        return IOFactory::load($base->getFile()->getPathname())->getActiveSheet();
    }

    private function sheetText(Worksheet $sheet): string
    {
        return collect($sheet->toArray(null, false, false))->flatten()->filter()->implode(' | ');
    }
}
