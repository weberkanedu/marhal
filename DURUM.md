# Marhal — Proje Durumu

> Yeni bir oturumda (Claude Code veya başka bir geliştirici) işe başlarken önce bu dosyayı,
> sonra [PROJECT.md](PROJECT.md) ve [SPEC.md](SPEC.md)'yi okuyun.

## Nerede kaldık

**Faz 1 (MVP) adımları**

| #   | Adım                                                               | Durum       |
| --- | ------------------------------------------------------------------ | ----------- |
| 0   | Altyapı: Laravel iskeleti, çok kiracılı yapı, paketler, şifreleme  | ✅          |
| 1   | Yolcular (liste, arama, ekleme, fotoğraf, TC doğrulama)            | ✅          |
| 2   | Turlar ve gruplar, yolcuların tura/gruba kaydı                     | ✅          |
| 3   | Ödemeler ve taksit planı                                           | 🚧 sıradaki |
| 4   | Raporlar (Excel / PDF)                                             | ⏳          |
| 5   | Kullanıcı yönetimi, platform paneli (acente açma, paket)           | ⏳          |
| 6   | Türkçeleştirme + **görsel tasarım** (kullanıcı onayladı) + kontrol | ⏳          |

Sonraki fazlar: Faz 2 oda/otobüs, Faz 3 uçuş/yaka kartı, Faz 4 API/entegrasyonlar.

## Alınan kararlar

- Yığın: Laravel 13 + Vue 3 + Inertia 3 + PostgreSQL 16, Docker.
- **Tur > Grup > Kayıt**: bir turda birden çok grup olabilir; her grubun rehberi var.
- Yolcu fotoğrafları şimdilik sunucu diskinde (`MEDIA_DISK=local`), ileride S3.
- Taksit takibi MVP'de (Faz 1, 3. adım).
- Görsel iyileştirmeler 6. adımda toplu yapılacak.
- Kayıt sayfası yok; acenteleri platform yöneticisi açar.

## Ortamlar

| Ortam      | Adres                        | Dal       | Durum                         |
| ---------- | ---------------------------- | --------- | ----------------------------- |
| Lokal      | http://localhost:8000        | —         | `docker compose up -d app db` |
| Staging    | https://staging.erkanicil.me | `staging` | ✅ Çalışıyor (demo veri)      |
| Production | https://app.erkanicil.me     | `main`    | ⏳ MVP + KVKK sonrası         |

Dokploy: proje "FullOnHertzner" → `marhal-staging`, `marhal-db-staging` (+ production karşılıkları).
Staging ayarları: Build Type = Dockerfile, Build Stage = `production`, domain portu **8080**.
Ortam değişkenleri yerelde `.env.staging` dosyasında (git'e girmez).

## Açık işler / hatırlatmalar

- [ ] **Kalıcı disk**: Dokploy'da `marhal-staging` (ve production) için
      `/var/www/html/storage/app` klasörüne volume mount. Yapılmazsa fotoğraflar her yayında silinir.
- [ ] **KVKK hukuki görüş** (Hetzner AB = yurt dışına aktarım) — production'a gerçek veri girmeden önce.
- [ ] **Yedekleme**: Postgres otomatik yedek + `ENCRYPTION_KEY`/`HASH_KEY` güvenli yedeği.
- [ ] `marhal-db-production` Dokploy'da **Deploy** edilmeli (oluşturmak başlatmıyor).
- [ ] Dokploy paneli HTTP üzerinden açılıyor; panele alan adı + HTTPS tanımlanmalı.

## Yerel geliştirme notları

- Bu bilgisayarda PHP yok; her şey Docker içinde (`docker compose exec app ...`).
- `vendor` ve `node_modules` Docker volume'unda (Windows dosya paylaşımı yavaş).
- Testler: `docker compose exec app php artisan test` · Analiz: `vendor/bin/phpstan analyse --memory-limit=1G`
- Yeni Vue sayfası ekledikten sonra testlerden önce `npm run build` gerekir (Vite manifest).
