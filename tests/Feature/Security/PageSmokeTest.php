<?php

namespace Tests\Feature\Security;

use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bütün GET sayfalarını her rolle otomatik gezer (yeni sayfalar da kendiliğinden kapsanır):
 *  - Hiçbir sayfa 500 vermemeli.
 *  - Rehberin aldığı hiçbir sayfa verisinde para veya kimlik bilgisi olmamalı.
 *  - Acente kullanıcılarının her sayfasında menü için modül listesi (features) dolu olmalı.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Rehbere asla gitmemesi gereken veri alanları (değeri null değilse sızıntı). */
    private const GUIDE_FORBIDDEN_KEYS = [
        'price', 'discount', 'net_price', 'paid', 'balance', 'overdue', 'total', 'default_price',
        'amount', 'national_id', 'passport_no', 'masked_national_id', 'masked_passport_no', 'outstanding',
    ];

    /** Otomatik gezintiye girmeyen rotalar (dosya indirme, çerçeve dışı akışlar). */
    private const SKIP = [
        'login', 'logout', 'password.*', 'verification.*', 'two-factor.*', 'passkey*', 'security.edit',
        'storage.*', 'reports.*', 'persons.photo', 'agency.logo', 'persons-lookup', 'persons.lookup', 'home',
    ];

    /** @var array<string, string> */
    private array $params = [];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'kurumsal')->value('id')]);
    }

    public function test_every_page_loads_for_every_role_without_leaks(): void
    {
        $guide = $this->user(UserRole::Guide);
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = Group::factory()->create(['tour_id' => $tour->id, 'guide_user_id' => $guide->id]);
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $registration = Registration::factory()->create(['tour_id' => $tour->id, 'group_id' => $group->id, 'person_id' => $person->id, 'price' => 1500]);
        Payment::factory()->create(['registration_id' => $registration->id, 'amount' => 500]);
        app(ReplaceInstallmentPlan::class)->handle($registration, [['due_date' => now()->subDay()->toDateString(), 'amount' => 1000]]);

        $users = [
            'admin' => $this->user(UserRole::Admin),
            'operasyon' => $this->user(UserRole::Operations),
            'rehber' => $guide,
            'platform' => User::factory()->superAdmin()->create(),
        ];

        $this->params = [
            'tour' => $tour->id, 'group' => $group->id, 'person' => $person->id,
            'registration' => $registration->id, 'tenant' => $this->tenant->id, 'user' => (string) $users['operasyon']->id,
        ];

        $visited = 0;

        foreach ($users as $role => $user) {
            foreach ($this->pageUrls() as $name => $url) {
                $response = $this->actingAs($user)->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->inertiaVersion()]);
                $status = $response->getStatusCode();
                $visited++;

                $this->assertLessThan(500, $status, "{$role} → {$name} ({$url}) sunucu hatası verdi: {$status}");

                if ($status !== 200 || ! $response->headers->has('X-Inertia')) {
                    continue;
                }

                $props = $response->json('props');

                if ($role === 'rehber') {
                    $this->assertNoForbiddenValues($props, "rehber → {$name}");
                }

                if (in_array($role, ['admin', 'operasyon', 'rehber'], true)) {
                    $this->assertNotEmpty($props['features'] ?? [], "{$role} → {$name}: menü için modül listesi boş");
                }
            }
        }

        $this->assertGreaterThan(40, $visited, 'Gezilen sayfa sayısı beklenenden az; rota listesi bozulmuş olabilir.');
    }

    /**
     * Parametreleri doldurulabilen bütün web GET rotaları.
     *
     * @return array<string, string>
     */
    private function pageUrls(): array
    {
        $urls = [];

        /** @var RouteDefinition $route */
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true) || ! in_array('web', $route->gatherMiddleware(), true)) {
                continue;
            }

            if (Str::is(self::SKIP, $name)) {
                continue;
            }

            $params = [];
            foreach ($route->parameterNames() as $param) {
                if (! isset($this->params[$param])) {
                    continue 2;
                }
                $params[$param] = $this->params[$param];
            }

            $urls[$name] = route($name, $params);
        }

        return $urls;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function assertNoForbiddenValues(array $data, string $context, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $here = $path === '' ? (string) $key : "{$path}.{$key}";

            if (is_string($key) && in_array($key, self::GUIDE_FORBIDDEN_KEYS, true)) {
                $this->assertTrue(
                    $value === null || $value === [] || $value === '',
                    "{$context}: rehbere '{$here}' verisi gönderildi.",
                );
            }

            if (is_array($value)) {
                $this->assertNoForbiddenValues($value, $context, $here);
            }
        }
    }

    private function user(UserRole $role): User
    {
        return User::factory()->forTenant($this->tenant)->role($role)->create();
    }

    private function inertiaVersion(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }
}
