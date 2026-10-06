<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\FeedbackStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 10c: platform geri bildirime yanıt verir; kullanıcı yalnız kendi gönderdiklerini "Gönderdiklerim"de görür;
 * platform → Acenteler tablosu tasarımdaki bilgileri taşır.
 */
class FeedbackReplyTest extends TestCase
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

    private function send(User $user, string $message): Feedback
    {
        $this->actingAs($user)->post(route('feedback.store'), ['type' => 'oneri', 'message' => $message])->assertSessionHasNoErrors();

        return Feedback::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_platform_replies_and_user_sees_it_in_sent_items(): void
    {
        $feedback = $this->send($this->staff, 'Aileleri aynı kata koyabilir miyiz?');
        $platform = User::factory()->superAdmin()->create();

        $this->actingAs($platform)->post(route('platform.feedback.reply', $feedback), ['reply' => 'Teşekkürler, yapıyoruz.'])
            ->assertSessionHasNoErrors();

        $feedback->refresh();
        $this->assertSame(FeedbackStatus::Replied, $feedback->status);
        $this->assertSame($platform->id, $feedback->replied_by);
        $this->assertNull($feedback->reply_seen_at);

        // Düğmedeki yeni yanıt sayısı, "Gönderdiklerim" açılınca söner.
        $this->actingAs($this->staff)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('feedbackUnread', 1));

        $this->actingAs($this->staff)->getJson(route('feedback.mine'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.reply', 'Teşekkürler, yapıyoruz.')
            ->assertJsonPath('items.0.status_label', 'Yanıtlandı')
            ->assertJsonPath('items.0.unseen', true)
            ->assertJsonPath('items.0.tracking', $feedback->trackingNo());

        $this->assertNotNull($feedback->fresh()?->reply_seen_at);
        $this->actingAs($this->staff)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('feedbackUnread', 0));
    }

    public function test_users_see_only_their_own_feedback(): void
    {
        $colleague = User::factory()->forTenant($this->tenant)->create();
        $otherTenant = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->forTenant($otherTenant)->create();

        $this->send($this->staff, 'Benim görüşüm');
        $this->send($colleague, 'Meslektaşımın görüşü');
        $this->send($stranger, 'Başka acentenin görüşü');

        $this->actingAs($this->staff)->getJson(route('feedback.mine'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.message', 'Benim görüşüm');
    }

    public function test_only_platform_admin_can_reply(): void
    {
        $feedback = $this->send($this->staff, 'Bir görüş');
        $admin = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post(route('platform.feedback.reply', $feedback), ['reply' => 'Kendi yanıtım'])->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->post(route('platform.feedback.reply', $feedback), ['reply' => ''])
            ->assertSessionHasErrors('reply');
        $this->assertSame(FeedbackStatus::New, $feedback->fresh()?->status);
    }

    public function test_inbox_lists_unanswered_first_with_counts(): void
    {
        $answered = $this->send($this->staff, 'Eski görüş');
        $answered->forceFill(['status' => FeedbackStatus::Replied, 'reply' => 'Tamam', 'replied_at' => now()])->save();
        $this->send($this->staff, 'Yeni görüş');

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('platform.feedback.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('platform/Feedback')
                ->where('newCount', 1)
                ->where('items.data.0.message', 'Yeni görüş')
                ->where('items.data.1.reply', 'Tamam')
                ->where('platformCounts.feedback', 1));
    }

    public function test_tenant_list_shows_city_state_renewal_usage_and_request(): void
    {
        $this->seed(PlanSeeder::class);
        $kafile = Plan::where('slug', 'kafile')->sole();
        Tenant::factory()->create([
            'name' => 'AAA Konya Tur', 'city' => 'Konya', 'plan_id' => $kafile->id,
            'status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(9)->addHour(),
            'requested_plan_id' => $kafile->id, 'requested_billing_cycle' => 'yillik', 'requested_at' => now(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('platform/Tenants')
                ->where('tenants.0.name', 'AAA Konya Tur')
                ->where('tenants.0.city', 'Konya')
                ->where('tenants.0.state.label', 'Deneme')
                ->where('tenants.0.renewal', 'Deneme · 10 gün kaldı')
                ->where('tenants.0.usage.passengers.limit', 1500)
                ->where('tenants.0.request.plan', 'Kafile')
                ->has('paymentOptions.plans', 4)
                ->where('platformCounts.tenants', 1));
    }

    public function test_agency_admin_can_set_city(): void
    {
        $admin = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post(route('agency.update'), [
            'name' => $this->tenant->name, 'default_currency' => 'TRY', 'city' => 'Kayseri',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Kayseri', $this->tenant->fresh()?->city);
    }
}
