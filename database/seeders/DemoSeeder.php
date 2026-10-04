<?php

namespace Database\Seeders;

use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Örnek veri. Lokalde tüm demo kullanıcıların şifresi "password"; internete açık
 * ortamlarda (staging) her hesap için rastgele güçlü şifre üretilir ve konsola yazılır.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [];
        $password = function (string $email) use (&$accounts): string {
            $plain = app()->isLocal() ? 'password' : Str::password(16, symbols: false);
            $accounts[$email] = $plain;

            return $plain;
        };

        User::factory()->superAdmin()->create([
            'name' => 'Platform Yöneticisi',
            'email' => 'platform@marhal.test',
            'password' => $password('platform@marhal.test'),
        ]);

        $tenant = Tenant::create([
            'name' => 'Demo Turizm',
            'slug' => 'demo-turizm',
            'plan_id' => Plan::where('slug', 'profesyonel')->value('id'),
            'status' => TenantStatus::Active,
            'default_currency' => 'USD',
            'phone' => '0212 555 00 00',
            'email' => 'info@demoturizm.test',
            'address' => 'Fatih, İstanbul',
        ]);

        User::factory()->forTenant($tenant)->create([
            'name' => 'Acente Yöneticisi',
            'email' => 'admin@marhal.test',
            'password' => $password('admin@marhal.test'),
        ]);

        User::factory()->forTenant($tenant)->role(UserRole::Operations)->create([
            'name' => 'Operasyon Personeli',
            'email' => 'operasyon@marhal.test',
            'password' => $password('operasyon@marhal.test'),
        ]);

        if (! app()->isLocal()) {
            $this->command->warn('Demo hesaplar (şifreleri bir yere not edin, tekrar gösterilmez):');
            foreach ($accounts as $email => $plain) {
                $this->command->line("  {$email}  {$plain}");
            }
        }

        app(CurrentTenant::class)->run($tenant, function () use ($tenant): void {
            $tour = Tour::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Ekim 2026 Umre Turu',
                'start_date' => now()->addWeeks(3),
                'end_date' => now()->addWeeks(5),
            ]);

            Tour::factory()->completed()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Ağustos 2026 Umre Turu',
            ]);

            $groups = [
                Group::factory()->create(['tour_id' => $tour->id, 'name' => 'A Grubu']),
                Group::factory()->create(['tour_id' => $tour->id, 'name' => 'B Grubu']),
            ];

            Person::factory()->count(12)->create(['tenant_id' => $tenant->id])
                ->each(function (Person $person, int $index) use ($tour, $groups): void {
                    $registration = Registration::factory()->create([
                        'tour_id' => $tour->id,
                        'group_id' => $groups[$index % 2]->id,
                        'person_id' => $person->id,
                        'status' => $index < 10 ? RegistrationStatus::Confirmed : RegistrationStatus::Pending,
                    ]);

                    if ($index < 8) {
                        Payment::factory()->create([
                            'registration_id' => $registration->id,
                            'amount' => $index < 4 ? 1500 : 750,
                            'method' => PaymentMethod::Transfer,
                            'paid_at' => now()->subDays(30),
                        ]);
                    }

                    // Taksitli yolcular: yarısı ödenmiş, ikinci taksitin vadesi geçmiş → gecikmiş borç görünür.
                    if ($index >= 4 && $index < 8) {
                        app(ReplaceInstallmentPlan::class)->handle($registration, [
                            ['due_date' => now()->subDays(30)->toDateString(), 'amount' => 750, 'notes' => 'Peşinat'],
                            ['due_date' => now()->subDays(5)->toDateString(), 'amount' => 750],
                        ]);
                    }
                });
        });
    }
}
