<?php

namespace App\Providers;

use App\Actions\Security\RecordLogin;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Security\SensitiveData;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);

        $this->app->singleton(SensitiveData::class, fn () => new SensitiveData(
            config('marhal.encryption_key'),
            config('marhal.hash_key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthAuditing();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Başarılı / başarısız girişleri audit log'a yazar (SPEC.md §1 audit_logs).
     */
    protected function configureAuthAuditing(): void
    {
        Event::listen(function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            // Hesap paylaşımı koruması (10d): cihaz, tek oturum, şüpheli kullanım.
            $guard = Auth::guard('web');
            $viaRemember = $guard instanceof SessionGuard && $guard->viaRemember();

            if (! app(RecordLogin::class)->handle($event->user, request(), $viaRemember)) {
                // "Beni hatırla" ile başka cihazdan şifresiz giriş: hesap şu an başka cihazda açık, şifre istenir.
                $guard instanceof SessionGuard && $guard->logoutCurrentDevice();
                session()->flash('status', 'Hesabınız başka bir cihazda açık. Devam etmek için şifrenizle giriş yapın.');

                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            app(AuditLogger::class)->log('login', user: $event->user);
        });

        Event::listen(function (Failed $event): void {
            app(AuditLogger::class)->log('login_failed', changes: [
                'email' => $event->credentials['email'] ?? null,
            ], user: $event->user instanceof User ? $event->user : null);
        });
    }
}
