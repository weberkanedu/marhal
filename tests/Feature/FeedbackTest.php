<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Görüşünü paylaş": her acente kullanıcısı gönderir, yalnız platform yöneticisi hepsini listeler.
 */
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    }

    public function test_any_agency_user_can_send_feedback_with_screen_path_only(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();

        $this->actingAs($guide)->post(route('feedback.store'), [
            'type' => 'hata',
            'rating' => 4,
            'message' => 'Oda planında uyarı çıkmadı',
            'screen' => '/tours/abc?q=Ahmet',
            'wants_reply' => true,
        ])->assertSessionHasNoErrors()->assertRedirect()
            // Panel teşekkür ekranında takip numarasını gösterir.
            ->assertInertiaFlash('feedback.no', Feedback::sole()->id);

        $feedback = Feedback::sole();
        $this->assertSame([$this->tenant->id, $guide->id, '/tours/abc', 4, 'yeni', true], [
            $feedback->tenant_id, $feedback->user_id, $feedback->screen, $feedback->rating, $feedback->status, $feedback->wants_reply,
        ]);

        // "Soru" türü ve ekran eklenmeden gönderim
        $this->actingAs($guide)->post(route('feedback.store'), ['type' => 'soru', 'message' => 'Bu nasıl yapılır?', 'screen' => null])
            ->assertSessionHasNoErrors();
        $this->assertNull(Feedback::query()->latest('id')->first()?->screen);
    }

    public function test_feedback_is_validated_and_status_cannot_be_forged(): void
    {
        $user = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($user)->post(route('feedback.store'), ['type' => 'x', 'rating' => 9, 'message' => ''])
            ->assertSessionHasErrors(['type', 'rating', 'message']);

        $this->actingAs($user)->post(route('feedback.store'), [
            'type' => 'oneri', 'message' => 'Güzel olmuş', 'status' => 'yanitlandi', 'reply' => 'x', 'tenant_id' => Tenant::factory()->create()->id,
        ]);

        $feedback = Feedback::sole();
        $this->assertSame(['yeni', null, $this->tenant->id], [$feedback->status, $feedback->reply, $feedback->tenant_id]);
    }

    public function test_only_platform_admin_lists_feedback_from_all_agencies(): void
    {
        $user = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();
        $this->actingAs($user)->post(route('feedback.store'), ['type' => 'begeni', 'message' => 'Teşekkürler']);

        $this->actingAs($user)->get(route('platform.feedback.index'))->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('platform.feedback.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/Feedback')
                ->has('items.data', 1)
                ->where('items.data.0.tenant', $this->tenant->name)
                ->where('items.data.0.type_label', 'Teşekkür'));
    }
}
