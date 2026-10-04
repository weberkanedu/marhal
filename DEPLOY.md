# Marhal — Yayın (Dokploy) kontrol listesi

Staging kurulumunda yaşanan sorunlar (Nixpacks seçili kalması, veritabanının hiç başlatılmaması,
yanlış port) tekrar etmesin diye her yeni ortamda bu liste **sırayla** uygulanır.

## 1. Veritabanı

- [ ] Dokploy → PostgreSQL servisi oluştur (ör. `marhal-db-production`).
- [ ] **Deploy'a bas** — oluşturmak servisi başlatmaz. Logs sekmesinde container görünmeli.
- [ ] Internal Connection URL'yi kopyala (`postgresql://...`) → ortam değişkeni `DB_URL`.
- [ ] Backups sekmesi: günlük otomatik yedek + saklama süresi; hedef S3/uzak depolama.
- [ ] **Geri yükleme denemesi**: yedeği boş bir veritabanına yükleyip uygulamanın açıldığını gör.

## 2. Uygulama

- [ ] Provider: GitHub → depo `marhal`, dal (`main` = production, `staging` = staging), Autodeploy açık.
- [ ] **Build Type: Dockerfile** (Nixpacks DEĞİL) · Docker File: `Dockerfile` · **Build Stage: `production`**.
- [ ] Domains: alan adı, **Container Port: 8080**, HTTPS açık, sertifika Let's Encrypt.
- [ ] DNS: alan adının A kaydı sunucu IP'sine yönlendirilmiş olmalı (Validate DNS yeşil).
- [ ] **Volume / kalıcı disk**: `/var/www/html/storage/app` → kalıcı volume. (Yolcu fotoğrafları, logolar.)
      Yapılmazsa her yayında dosyalar silinir.

## 3. Ortam değişkenleri

Şablon: `.env.example`. Gizli değerler **kullanıcı tarafından** panele girilir, git'e girmez;
yerelde `.env.staging` / `.env.production` olarak saklanır ve şifre yöneticisine yedeklenir.

- [ ] `APP_ENV=production` (staging için `staging`), `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] `APP_KEY`, `ENCRYPTION_KEY`, `HASH_KEY` — **her ortam için ayrı üret** (`php artisan marhal:keys --show`).
      `ENCRYPTION_KEY` / `HASH_KEY` kaybolursa kimlik/pasaport verisi geri gelmez.
- [ ] `DB_CONNECTION=pgsql`, `DB_URL=...`
- [ ] `LOG_CHANNEL=stderr`, `SESSION_SECURE_COOKIE=true`, `MEDIA_DISK=local` (S3'e geçişte `s3`)
- [ ] E-posta: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- [ ] `SENTRY_LARAVEL_DSN` (hata takibi; kişisel veri gönderimi kapalı)

## 4. İlk yayın sonrası

- [ ] Deployments: son yayın **Done**; Logs: "Running migrations ... DONE", hata yok.
- [ ] `curl https://<alan>/up` → 200.
- [ ] Terminal (container): `php artisan db:seed --class=PlanSeeder --force`
- [ ] Platform yöneticisi: `php artisan marhal:super-admin` (şifreyi **kullanıcı** girer).
- [ ] Production'a `DemoSeeder` **çalıştırılmaz**.
- [ ] Giriş → platform → yeni acente aç → acente yöneticisiyle giriş → yolcu ekle → PDF indir (duman testi).

## 5. Production'a özel ön koşullar

- [ ] KVKK hukuki görüş (yurt dışında barındırma, açık rıza metni, saklama süreleri).
- [ ] Dokploy paneline alan adı + HTTPS (panel şu an `http://IP:3000`).
- [ ] GitHub Actions testleri `main` için yeşil.
