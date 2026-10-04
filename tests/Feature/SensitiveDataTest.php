<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensitiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_id_and_passport_are_encrypted_at_rest(): void
    {
        $person = Person::factory()->create(['national_id' => '12345678901', 'passport_no' => 'u 1234567']);

        $row = DB::table('persons')->where('id', $person->id)->first();

        $this->assertNotSame('12345678901', $row->national_id);
        $this->assertStringNotContainsString('12345678901', $row->national_id);
        $this->assertSame('12345678901', $person->fresh()->national_id);
        $this->assertSame('U1234567', $person->fresh()->passport_no, 'Boşluklar temizlenir, büyük harfe çevrilir.');
    }

    public function test_people_can_be_found_by_national_id_and_passport(): void
    {
        $person = Person::factory()->create(['national_id' => '12345678901', 'passport_no' => 'U1234567']);
        Person::factory()->count(3)->create();

        $this->assertTrue(Person::whereNationalId('12345678901')->first()->is($person));
        $this->assertTrue(Person::wherePassportNo('u1234567')->first()->is($person));
    }

    public function test_national_id_is_unique_per_tenant(): void
    {
        [$a, $b] = Tenant::factory()->count(2)->create();
        Person::factory()->create(['tenant_id' => $a->id, 'national_id' => '12345678901']);
        Person::factory()->create(['tenant_id' => $b->id, 'national_id' => '12345678901']);

        $this->expectException(QueryException::class);
        Person::factory()->create(['tenant_id' => $a->id, 'national_id' => '12345678901']);
    }

    public function test_sensitive_fields_are_hidden_and_masked_in_output(): void
    {
        $person = Person::factory()->create(['national_id' => '12345678901', 'passport_no' => 'U1234567']);

        $data = $person->fresh()->toArray();

        $this->assertArrayNotHasKey('national_id', $data);
        $this->assertArrayNotHasKey('passport_no', $data);
        $this->assertArrayNotHasKey('national_id_hash', $data);
        $this->assertSame('*******8901', $data['masked_national_id']);
        $this->assertSame('*****567', $data['masked_passport_no']);
    }

    public function test_audit_log_does_not_contain_sensitive_values(): void
    {
        $tenant = Tenant::factory()->create();
        $person = app(CurrentTenant::class)->run($tenant, fn () => Person::factory()->create([
            'tenant_id' => $tenant->id,
            'national_id' => '12345678901',
        ]));

        $log = AuditLog::where('subject_id', $person->id)->where('action', 'create')->sole();

        $this->assertSame('[gizli]', $log->changes['national_id']);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertStringNotContainsString('12345678901', json_encode($log->changes));
    }

    public function test_passport_validity_check(): void
    {
        $person = Person::factory()->make(['passport_expiry_date' => now()->addMonths(7)]);

        $this->assertTrue($person->passportValidFor(now()));
        $this->assertFalse($person->passportValidFor(now()->addMonths(2)));
    }
}
