# Marhal

Umre / Hac acenteleri için çok kiracılı (multi-tenant) organizasyon yönetim yazılımı.
Ürün tanımı için [PROJECT.md](PROJECT.md), teknik şartname için [SPEC.md](SPEC.md).

**Yığın:** Laravel 13 · Vue 3 · Inertia 3 · TypeScript · Tailwind 4 · PostgreSQL 16 · Docker

## Lokal kurulum

Gerekli olan tek şey **Docker Desktop**. PHP veya Node'u bilgisayara kurmanız gerekmez.

```bash
# 1. Ortam dosyası
cp .env.example .env

# 2. İmajı derle, veritabanını başlat
docker compose build
docker compose up -d db

# 3. Bağımlılıklar, anahtarlar, veritabanı
docker compose run --rm app composer install
docker compose run --rm app npm install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan marhal:keys
docker compose run --rm app php artisan migrate --seed

# 4. Uygulamayı başlat (arayüz canlı yenilemeli)
docker compose --profile dev up
```

Tarayıcıda: http://localhost:8000

### Demo hesaplar (sadece lokal, şifre: `password`)

| E-posta                 | Rol                     |
| ----------------------- | ----------------------- |
| `platform@marhal.test`  | Platform yöneticisi     |
| `admin@marhal.test`     | Demo Turizm — yönetici  |
| `operasyon@marhal.test` | Demo Turizm — operasyon |

## Sık kullanılan komutlar

```bash
docker compose run --rm app php artisan test        # testler
docker compose run --rm app vendor/bin/pint         # PHP kod biçimi
docker compose run --rm app vendor/bin/phpstan analyse --memory-limit=1G
docker compose run --rm app npm run check:fix       # JS/Vue biçim + lint
docker compose run --rm app php artisan migrate:fresh --seed   # veritabanını sıfırla
```

## Mimari özet

| Konu                  | Nerede                                                            |
| --------------------- | ----------------------------------------------------------------- |
| Acente izolasyonu     | `app/Models/Concerns/BelongsToTenant.php`, `app/Support/Tenancy/` |
| Paket / özellik       | `app/Support/Features/FeatureGate.php`, `feature:` middleware     |
| Roller                | `app/Enums/UserRole.php`, `role:` middleware                      |
| Kimlik/pasaport şifre | `app/Casts/EncryptedWithBlindIndex.php`, `app/Support/Security/`  |
| Erişim kayıtları      | `app/Models/Concerns/Auditable.php`, `app/Support/Audit/`         |
| Bakiye hesabı         | `app/Models/Registration.php` (`balance()`, `withPaidTotal()`)    |

Acente ekranlarına giden rotalar `tenant` middleware'i ile korunur:

```php
Route::middleware(['auth', 'verified', 'tenant', 'feature:room_planning'])->group(...);
```

## Deploy (Dokploy)

- Build: `Dockerfile`, hedef (target) **`production`**, port **8080**
- `staging` dalı → staging.erkanicil.me, `main` dalı → app.erkanicil.me
- Konteyner açılışında migration otomatik çalışır (`AUTORUN_LARAVEL_MIGRATION=true`)
- Dokploy panelinde tanımlanacak değişkenler: SPEC.md §8a
  (`APP_KEY`, `DB_*`, `ENCRYPTION_KEY`, `HASH_KEY`, `SENTRY_LARAVEL_DSN` …)
- İlk kurulumda: `php artisan db:seed --class=PlanSeeder` ve `php artisan marhal:super-admin`

> ⚠️ `ENCRYPTION_KEY` ve `HASH_KEY` kaybolursa şifreli kimlik/pasaport verileri geri getirilemez.
