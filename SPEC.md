# Marhal — Umre Organizasyon Yönetim Yazılımı — SPEC.md

> Sürüm 3 — Tur > Grup hiyerarşisi, yolcu fotoğrafı ve taksit takibi MVP'ye alındı.
> Teknik yığın: Laravel 13 + Vue 3 + Inertia.js + PostgreSQL (bkz. §8).

## 0. Temel Kavramlar

- **Tenant**: Sistemi kullanan acente. Tüm acente verisi `tenant_id` ile izole edilir.
- **Kişi (person)**: Acentenin müşterisi. Kimlik/pasaport bilgisi kişiye aittir ve
  turdan bağımsızdır; aynı kişi yıllar içinde birden fazla tura katılabilir.
- **Tur (tour)**: Bir sefer (örn. "Ekim 2026 Umre Turu"). Tarih, fiyat ve kapasite turda tutulur.
- **Grup (group)**: Turun içindeki alt grup (örn. "A Grubu"). Her grubun kendi rehberi olur;
  oda, otobüs ve uçuş organizasyonu grup bazında yapılır. Bir turda bir veya birden çok grup olabilir.
- **Kayıt (registration)**: Bir kişinin bir tura katılımı (isteğe bağlı olarak bir gruba atanmış).
  Paket ücreti, oda tipi, ödeme durumu kayda bağlıdır.
- **Ödeme (payment)**: Bir kayda yapılan tek bir tahsilat hareketi.
  Bakiye her zaman hesaplanır, saklanmaz.

## 1. Veri Modeli

Ortak kurallar (tüm iş tablolarında):

- `id` uuid PK (yalnızca `users` ve `audit_logs` bigint — Laravel/Fortify uyumu için), `created_at`, `updated_at`
- `tenant_id` (tenants ve plans hariç) — her sorguda zorunlu filtre (global scope)
- `deleted_at` (yumuşak silme) — persons, tours, groups, registrations, payments, users
- Para alanları `decimal(12,2)` + yanında `currency` (ISO 4217: TRY / USD / EUR / SAR)

### plans (Üyelik paketleri — platform geneli)

| Alan              | Tip           | Açıklama                                               |
| ----------------- | ------------- | ------------------------------------------------------ |
| id                | uuid          | PK                                                     |
| name              | string        | Başlangıç / Profesyonel / Kurumsal                     |
| price_monthly     | decimal       |                                                        |
| price_yearly      | decimal       |                                                        |
| currency          | string(3)     |                                                        |
| user_limit        | int, nullable | null = sınırsız                                        |
| active_tour_limit | int, nullable | Aynı anda aktif (bitmemiş) tur sayısı; null = sınırsız |

### plan_features

| Alan        | Tip        | Açıklama |
| ----------- | ---------- | -------- |
| id          | uuid       | PK       |
| plan_id     | fk → plans |          |
| feature_key | string     | bkz. §2  |
| enabled     | bool       |          |

### tenants (Acenteler)

| Alan                           | Tip                   | Açıklama                               |
| ------------------------------ | --------------------- | -------------------------------------- |
| id                             | uuid                  | PK                                     |
| name                           | string                | Acente adı                             |
| slug                           | string, unique        |                                        |
| plan_id                        | fk → plans            |                                        |
| status                         | enum                  | active / suspended / trial             |
| trial_ends_at                  | timestamp, nullable   |                                        |
| subscription_ends_at           | timestamp, nullable   |                                        |
| tursab_no                      | string, nullable      | TÜRSAB belge no                        |
| default_currency               | string(3)             | Varsayılan paket para birimi           |
| phone, email, website, address | string/text, nullable | Yaka kartı ve raporlarda firma bilgisi |
| logo_path                      | string, nullable      | Firma logosu (yaka kartı, PDF)         |

### tenant_feature_overrides (opsiyonel)

Paketten bağımsız olarak tek bir acenteye özellik açıp/kapatmak için.

| Alan                                | Tip |
| ----------------------------------- | --- |
| id, tenant_id, feature_key, enabled |     |

### users (Kullanıcılar)

| Alan          | Tip                        | Açıklama                                 |
| ------------- | -------------------------- | ---------------------------------------- |
| id            | uuid                       | PK                                       |
| tenant_id     | fk → tenants, **nullable** | Platform yöneticisinde null              |
| name          | string                     |                                          |
| email         | string, unique             |                                          |
| password      | string                     | argon2id/bcrypt hash                     |
| role          | enum                       | super_admin / admin / operasyon / rehber |
| is_active     | bool                       |                                          |
| last_login_at | timestamp                  |                                          |

Roller:

| Rol         | Kapsam                                                                                                                                                    |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| super_admin | Platform sahibi. Acente, paket, abonelik yönetimi. Acente iş verisini (yolcu, ödeme) görmez; destek için ayrı "impersonate" akışı ve log gerekir (Faz 2). |
| admin       | Acentenin tüm verisi; kullanıcı yönetimi; kimlik/pasaport no görme.                                                                                       |
| operasyon   | Kişi, grup, kayıt, ödeme CRUD. Kimlik/pasaport no **maskeli** görür (`*******1234`).                                                                      |
| rehber      | Yalnızca kendisine atanmış grupların yolcu listesi (ad, telefon, acil durum kişisi, oda/otobüs). Ödeme ve kimlik bilgisi görmez.                          |

### persons (Kişiler)

| Alan                    | Tip                 | Açıklama                                                           |
| ----------------------- | ------------------- | ------------------------------------------------------------------ |
| id                      | uuid                | PK                                                                 |
| tenant_id               | fk                  |                                                                    |
| first_name              | string              |                                                                    |
| last_name               | string              |                                                                    |
| gender                  | enum                | erkek / kadın — oda yerleşimi için zorunlu                         |
| birth_date              | date                |                                                                    |
| nationality             | string(2)           | ISO ülke kodu, varsayılan TR                                       |
| national_id             | text (encrypted)    | T.C. Kimlik No                                                     |
| national_id_hash        | string, index       | HMAC-SHA256(national_id, HASH_KEY) — arama ve tekrar kontrolü için |
| passport_no             | text (encrypted)    |                                                                    |
| passport_no_hash        | string, index       |                                                                    |
| passport_issue_date     | date, nullable      |                                                                    |
| passport_expiry_date    | date, nullable      | Vize için en az 6 ay geçerlilik kontrolü                           |
| phone                   | string              |                                                                    |
| email                   | string, nullable    |                                                                    |
| address                 | text, nullable      |                                                                    |
| emergency_contact_name  | string, nullable    |                                                                    |
| emergency_contact_phone | string, nullable    |                                                                    |
| photo_path              | string, nullable    | Dosya yolu (MVP: sunucu diski, ileride S3) — bkz. §7               |
| notes                   | text, nullable      |                                                                    |
| kvkk_consent_at         | timestamp, nullable | Aydınlatma metni onayı / açık rıza tarihi                          |

Kısıtlar: `(tenant_id, national_id_hash)` unique (null hariç).

### person_relations (Yakınlık — Faz 2, tablo MVP'de oluşturulabilir)

| Alan              | Tip          | Açıklama                                  |
| ----------------- | ------------ | ----------------------------------------- |
| id                | uuid         |                                           |
| tenant_id         | fk           |                                           |
| person_id         | fk → persons |                                           |
| related_person_id | fk → persons |                                           |
| relation          | enum         | eş / anne / baba / çocuk / kardeş / diğer |

### tours (Turlar)

| Alan          | Tip               | Açıklama                                        |
| ------------- | ----------------- | ----------------------------------------------- |
| id            | uuid              | PK                                              |
| tenant_id     | fk                |                                                 |
| name          | string            | Örn. "Ekim 2026 Umre Turu"                      |
| type          | enum              | umre / hac                                      |
| start_date    | date              |                                                 |
| end_date      | date              |                                                 |
| status        | enum              | taslak / satışta / kapandı / tamamlandı / iptal |
| capacity      | int, nullable     |                                                 |
| default_price | decimal, nullable | Kayıt açılırken önerilen fiyat                  |
| currency      | string(3)         |                                                 |
| notes         | text              |                                                 |

"Aktif tur" = status ∈ {taslak, satışta, kapandı} ve end_date ≥ bugün. Paket limiti buna uygulanır.

### groups (Tur içindeki gruplar)

| Alan          | Tip                  | Açıklama                                 |
| ------------- | -------------------- | ---------------------------------------- |
| id            | uuid                 | PK                                       |
| tenant_id     | fk                   |                                          |
| tour_id       | fk → tours           |                                          |
| name          | string               | Örn. "A Grubu" — tur içinde tekil        |
| guide_user_id | fk → users, nullable | Rehber kullanıcı (rol: rehber)           |
| guide_name    | string, nullable     | Sistemde kullanıcısı olmayan rehber için |
| guide_phone   | string, nullable     |                                          |
| notes         | text                 |                                          |

Tek gruplu turlarda arayüz grup adımını otomatik geçer (varsayılan grup).

### tour_hotels (Turun konaklayacağı oteller — Faz 2)

| Alan                                                  | Tip |
| ----------------------------------------------------- | --- |
| id, tenant_id, tour_id, hotel_id, check_in, check_out |     |

### registrations (Kayıtlar — kişi × tur)

| Alan          | Tip                   | Açıklama                            |
| ------------- | --------------------- | ----------------------------------- |
| id            | uuid                  | PK                                  |
| tenant_id     | fk                    |                                     |
| tour_id       | fk → tours            |                                     |
| group_id      | fk → groups, nullable | Henüz gruba atanmamışsa null        |
| person_id     | fk → persons          |                                     |
| room_type     | enum                  | 2'li / 3'lü / 4'lü / 5'li           |
| price         | decimal               | Bu kişi için anlaşılan paket ücreti |
| discount      | decimal               | Varsayılan 0                        |
| currency      | string(3)             |                                     |
| status        | enum                  | ön_kayıt / kesin_kayıt / iptal      |
| cancelled_at  | timestamp, nullable   |                                     |
| cancel_reason | text, nullable        |                                     |
| registered_at | timestamp             |                                     |
| notes         | text                  |                                     |

Kısıtlar: `(tour_id, person_id)` unique.
Hesaplanan: `net_price = price − discount`, `paid = Σ payments.amount_in_registration_currency`,
`balance = net_price − paid`.

### payments (Tahsilatlar — her satır tek hareket)

| Alan                            | Tip                | Açıklama                                                  |
| ------------------------------- | ------------------ | --------------------------------------------------------- |
| id                              | uuid               | PK                                                        |
| tenant_id                       | fk                 |                                                           |
| registration_id                 | fk → registrations |                                                           |
| amount                          | decimal            | Ödenen tutar (ödeme para biriminde)                       |
| currency                        | string(3)          | Ödemenin yapıldığı para birimi                            |
| exchange_rate                   | decimal(12,6)      | Ödeme para birimi → kayıt para birimi; aynıysa 1          |
| amount_in_registration_currency | decimal            | amount × exchange_rate (kayıt anında hesaplanıp saklanır) |
| method                          | enum               | nakit / havale / kredi_kartı / diğer                      |
| paid_at                         | date               |                                                           |
| type                            | enum               | tahsilat / iade — iade negatif etki eder                  |
| reference                       | string, nullable   | Dekont/makbuz no                                          |
| received_by                     | fk → users         |                                                           |
| notes                           | text               |                                                           |

### installments (Taksit planı — MVP)

| Tablo        | Alanlar                                                 |
| ------------ | ------------------------------------------------------- |
| installments | id, tenant_id, registration_id, due_date, amount, notes |

Ödemeler taksitlere tek tek eşlenmez; ödenen toplam vadesi gelen taksit toplamıyla
karşılaştırılarak "vadesi geçmiş borç" hesaplanır (borçlu yolcu listesi bu bilgiyi gösterir).

### hotels / rooms / room_assignments (Faz 2)

| Tablo            | Alanlar                                                                               |
| ---------------- | ------------------------------------------------------------------------------------- |
| hotels           | id, tenant_id, city (mekke / medine / diğer), name, address                           |
| rooms            | id, tenant_id, tour_hotel_id, floor, room_no, capacity, gender (erkek / kadın / aile) |
| room_assignments | id, tenant_id, room_id, registration_id                                               |

Kural: `gender = aile` olmayan odaya karşı cinsiyetten kişi atanamaz; aile odası
yalnızca `person_relations` ile bağlı kişilere verilir.

### buses / seat_assignments (Faz 2)

| Tablo            | Alanlar                                                                             |
| ---------------- | ----------------------------------------------------------------------------------- |
| buses            | id, tenant_id, tour_id, group_id (nullable), bus_no, plate, capacity, guide_user_id |
| seat_assignments | id, tenant_id, bus_id, seat_no, registration_id                                     |

### flights / flight_assignments (Faz 3)

| Tablo              | Alanlar                                                                                                                                 |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------- |
| flights            | id, tenant_id, tour_id, airline, flight_no, direction (gidiş / dönüş), departure_airport, arrival_airport, departure_time, arrival_time |
| flight_assignments | id, tenant_id, flight_id, registration_id, pnr                                                                                          |

### audit_logs (Erişim ve değişiklik kayıtları — MVP)

| Alan         | Tip             | Açıklama                                                                  |
| ------------ | --------------- | ------------------------------------------------------------------------- |
| id           | bigint          | PK                                                                        |
| tenant_id    | fk, nullable    |                                                                           |
| user_id      | fk → users      |                                                                           |
| action       | string          | view_sensitive / create / update / delete / export / login / login_failed |
| subject_type | string          | Örn. `person`                                                             |
| subject_id   | uuid            |                                                                           |
| changes      | jsonb, nullable | Değişen alanlar (hassas alanların değeri yazılmaz, sadece adı)            |
| ip           | inet            |                                                                           |
| user_agent   | string          |                                                                           |
| created_at   | timestamp       |                                                                           |

Bu tablo yalnızca eklenir (append-only); uygulama kullanıcısı silemez/güncelleyemez.

## 2. Feature Flag Mekanizması

Her modül bir `feature_key` ile tanımlanır:

| feature_key          | Modül                           |
| -------------------- | ------------------------------- |
| `passengers`         | Kişi + kayıt yönetimi           |
| `payments`           | Ödeme takibi                    |
| `basic_reports`      | Yolcu / ödeme listesi Excel-PDF |
| `room_planning`      | Oda yerleşimi                   |
| `bus_planning`       | Otobüs yerleşimi                |
| `flight_lists`       | Uçuş listeleri                  |
| `badge_generation`   | Yaka kartı                      |
| `advanced_reporting` | Gelişmiş raporlar               |
| `api_access`         | Harici REST API                 |

Erişim = `tenant_feature_overrides` varsa o, yoksa `plan_features`.
Kontrol bir Laravel middleware'i (`feature:room_planning`) ve frontend'e paylaşılan
`features` dizisi ile yapılır. Sonuç tenant başına cache'lenir; plan değişince cache temizlenir.
Limit kontrolleri (kullanıcı, aktif tur) ilgili kayıt oluşturulurken policy içinde yapılır.

## 3. Başlangıç Paket Matrisi

| Özellik               | Başlangıç | Profesyonel | Kurumsal |
| --------------------- | --------- | ----------- | -------- |
| Kişi / kayıt yönetimi | ✅        | ✅          | ✅       |
| Ödeme takibi          | ✅        | ✅          | ✅       |
| Temel raporlar        | ✅        | ✅          | ✅       |
| Oda planı             | ❌        | ✅          | ✅       |
| Otobüs planı          | ❌        | ✅          | ✅       |
| Uçuş listesi          | ❌        | ✅          | ✅       |
| Yaka kartı            | ❌        | ❌          | ✅       |
| Gelişmiş raporlar     | ❌        | ❌          | ✅       |
| Aynı anda aktif tur   | 1         | 5           | Sınırsız |
| Kullanıcı             | 1         | 5           | Sınırsız |
| API erişimi           | ❌        | ❌          | ✅       |

## 4. Rotalar (MVP)

Arayüz Inertia ile sunulur; aşağıdakiler web rotalarıdır (oturum + CSRF).
Harici REST API (`/api/v1/...`, token ile) Faz 4'te `api_access` özelliğiyle açılır.

```
# Kimlik doğrulama
GET    /login                     POST /login             POST /logout
GET    /forgot-password           POST /forgot-password
GET    /reset-password/{token}    POST /reset-password

# Platform yönetimi (super_admin)
GET    /platform/tenants          POST /platform/tenants
GET    /platform/tenants/{id}     PUT  /platform/tenants/{id}
GET    /platform/plans            PUT  /platform/plans/{id}

# Acente
GET    /dashboard
GET    /users                     POST /users             PUT /users/{id}     DELETE /users/{id}
GET    /persons                   POST /persons           GET /persons/{id}
PUT    /persons/{id}              DELETE /persons/{id}
POST   /persons/{id}/reveal       (tam kimlik/pasaport no — sadece admin, audit log yazar)
GET    /tours                     POST /tours             GET /tours/{id}
PUT    /tours/{id}                DELETE /tours/{id}
POST   /tours/{id}/groups         PUT /groups/{id}        DELETE /groups/{id}
POST   /tours/{id}/registrations              (kişiyi tura kaydet, isteğe bağlı grup)
PUT    /registrations/{id}                    (fiyat, oda tipi, durum)
DELETE /registrations/{id}                    (iptal → status=iptal)
GET    /registrations/{id}/payments           POST /registrations/{id}/payments
PUT    /payments/{id}             DELETE /payments/{id}
GET    /reports/tours/{id}/passengers?group_id=&format=xlsx|pdf
GET    /reports/tours/{id}/payments?format=xlsx|pdf
```

## 5. Güvenlik Gereksinimleri

- `national_id`, `passport_no`: Laravel `encrypted` cast (AES-256-CBC + MAC), anahtar `APP_KEY`'den
  ayrı tutulmak üzere `ENCRYPTION_KEY`.
- Arama/tekrar kontrolü: `*_hash` sütunları, HMAC-SHA256 ile ayrı bir `HASH_KEY` kullanılarak.
- Şifreler argon2id (Laravel `Hash`), giriş denemelerinde rate limit (5 deneme / dakika).
- Tenant izolasyonu: `BelongsToTenant` trait + global scope; yeni kayıtlarda `tenant_id` otomatik.
  Testlerde "başka tenant'ın kaydına erişim 404 döner" senaryosu her modül için zorunlu.
- Yetki: Laravel Policy'leri rol tablosuna göre (§1 users).
- Hassas alanlar varsayılan olarak maskeli gönderilir; tam değer yalnızca `reveal` uç noktasıyla,
  audit log ile.
- Sentry: `send_default_pii=false`, istek gövdesi ve hassas alan adları scrub edilir.
- Oturum çerezi `secure`, `httponly`, `samesite=lax`; tüm trafik HTTPS (Traefik).
- Yedekleme: günlük Postgres yedeği, şifreli olarak ayrı bir lokasyona (Dokploy backup).

## 6. KVKK

- **Yurt dışına aktarım**: Hetzner sunucuları AB'de (Almanya/Finlandiya). KVKK md. 9 kapsamında
  yurt dışına aktarım sayılır → standart sözleşme + KVKK'ya bildirim gerekir. Alternatif:
  Türkiye'de barındırma. **Hukuki görüş alınmalı; production'a gerçek veri girmeden önce karar verilmeli.**
- **Özel nitelikli veri**: Umre/Hac katılımı kişinin dini inancını dolaylı olarak gösterebilir
  (KVKK md. 6). Pasaport fotoğrafı da biyometrik sayılabilir. → açık rıza alınması önerilir.
- Acente ↔ Marhal arasında **veri işleyen sözleşmesi**; acente veri sorumlusudur.
- Acente, yolcudan aydınlatma metni onayı / açık rıza alır; sistem bunun tarihini (`kvkk_consent_at`) tutar.
- **Saklama süresi**: Tur bitiminden X yıl sonra (muhasebe yükümlülüğüne göre — örn. 10 yıl ödeme,
  2 yıl kimlik/pasaport) hassas alanlar otomatik anonimleştirilir. Süreler hukuki görüşle netleşecek.
- İlgili kişi başvurusu: admin bir kişinin tüm verisini dışa aktarabilir ve silebilir/anonimleştirebilir (Faz 2).

## 7. Dosya / Rapor Üretimi

- Yolcu fotoğrafı ve firma logosu: `config('marhal.media_disk')` diski. MVP'de sunucu diski
  (`storage/app/private`, herkese açık değil — yalnızca yetkili kullanıcıya uygulama üzerinden verilir).
  İleride `MEDIA_DISK=s3` ile S3 uyumlu depolamaya kod değişmeden geçilir.

- Excel: `maatwebsite/excel` (xlsx)
- PDF: `barryvdh/laravel-dompdf` (MVP); yaka kartı için ileride Browsershot/Gotenberg değerlendirilecek
- Büyük dışa aktarımlar kuyrukta (Laravel queue, database driver) üretilir.
- Her dışa aktarım audit log'a `export` olarak yazılır.

## 8. Teknik Yığın (Kesinleşti)

| Katman           | Seçim                                                                        |
| ---------------- | ---------------------------------------------------------------------------- |
| Backend          | Laravel 13 (PHP 8.4) + Fortify (giriş, 2FA, passkey)                         |
| Frontend         | Vue 3 + Inertia.js 3 + TypeScript + Tailwind CSS 4 (Laravel Vue starter kit) |
| Veritabanı       | PostgreSQL 16                                                                |
| Kuyruk / cache   | Database driver (MVP); ileride Redis                                         |
| Test             | PHPUnit + Larastan (PHPStan)                                                 |
| Lokal geliştirme | Docker Compose (PHP-FPM + Nginx/Caddy + Postgres + Vite)                     |
| Deploy           | Dokploy, Dockerfile ile; `staging` → staging, `main` → production            |
| Hata izleme      | Sentry (`sentry/sentry-laravel`)                                             |

## 8a. Ortam Değişkenleri

Her ortamın değişkenleri Dokploy panelinden tanımlanır; `.env` asla commit edilmez
(`.env.example` commit edilir):

- `APP_KEY`, `APP_URL`, `APP_ENV`
- `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `ENCRYPTION_KEY` (kimlik/pasaport şifreleme)
- `HASH_KEY` (kimlik/pasaport arama hash'i)
- `SENTRY_LARAVEL_DSN`
- Object storage anahtarları (eklendiğinde)

**Uyarı:** `ENCRYPTION_KEY` ve `HASH_KEY` kaybolursa şifreli veriler geri dönülemez şekilde kaybolur.
Ayrı ve güvenli bir yerde yedeklenmeli; değiştirilmesi için key rotation komutu gerekir.

## 9. Açık Kararlar

- [x] Backend framework → Laravel 13
- [x] Frontend framework → Vue 3 + Inertia
- [ ] Barındırma lokasyonu (KVKK md. 9) — AB mi, Türkiye mi?
- [ ] Çoklu dil desteği gerekli mi? (Arayüz şimdilik Türkçe; metinler `lang/tr` üzerinden, ileride en/ar eklenebilir)
- [ ] Mobil uygulama mı, responsive web yeterli mi? (MVP: responsive web)
- [ ] Ödeme hatırlatma bildirimleri (SMS/WhatsApp/e-posta) hangi fazda?
- [ ] Abonelik ödemesi nasıl alınacak? (iyzico / PayTR / manuel fatura)
- [ ] Hassas veri saklama süreleri
