# Marhal — Proje Durumu

> Yeni bir oturumda (Claude Code veya başka bir geliştirici) işe başlarken önce bu dosyayı,
> sonra [PROJECT.md](PROJECT.md) ve [SPEC.md](SPEC.md)'yi okuyun.

## Nerede kaldık

**Faz 1 (MVP) adımları**

| #   | Adım                                                               | Durum |
| --- | ------------------------------------------------------------------ | ----- |
| 0   | Altyapı: Laravel iskeleti, çok kiracılı yapı, paketler, şifreleme  | ✅    |
| 1   | Yolcular (liste, arama, ekleme, fotoğraf, TC doğrulama)            | ✅    |
| 2   | Turlar ve gruplar, yolcuların tura/gruba kaydı                     | ✅    |
| 3   | Ödemeler ve taksit planı                                           | ✅    |
| 4   | Raporlar (Excel / PDF)                                             | ✅    |
| 5   | Kullanıcı yönetimi, platform paneli (acente açma, paket)           | ✅    |
| 6   | Türkçeleştirme + **görsel tasarım** (kullanıcı onayladı) + kontrol | ✅    |

Sonraki fazlar: Faz 2 oda/otobüs, Faz 3 uçuş/yaka kartı, Faz 4 API/entegrasyonlar.

## Gereksinim takibi (müşterinin ilk listesi)

Her adım sonunda güncellenir. ✅ bitti · 🟡 kısmen · ⏳ planlı

| Modül / madde                                     | Durum | Nerede / ne zaman                                   |
| ------------------------------------------------- | ----- | --------------------------------------------------- |
| 1. Ad soyad, iletişim, TC/pasaport, doğum tarihi  | ✅    | Yolcular                                            |
| 1. Kişi fotoğrafı, acil durum bilgisi             | ✅    | Yolcular                                            |
| 1. Kayıt ve grup bilgileri, yolcu durum takibi    | ✅    | Tur detayı (kayıt durumu)                           |
| 1. Mekke / Medine otel bilgileri                  | ⏳    | Faz 2 (oteller + oda)                               |
| 2. Toplam ücret, kalan bakiye                     | ✅    | Tur detayı, yolcu detayı                            |
| 2. Ödeme girişi, yöntem, tarih, geçmiş            | ✅    | Kayıt ödeme sayfası                                 |
| 2. Taksit takibi                                  | ✅    | Taksit planı + gecikme                              |
| 2. Borçlu / tamamlanan listeleri, tahsilat raporu | ✅    | Tahsilat ekranı + Excel/PDF                         |
| 3. Oda yerleşim planı (otel/kat/oda, boş/dolu)    | ⏳    | Faz 2                                               |
| 4. Otobüs yerleşim planı (koltuk, rehber)         | ⏳    | Faz 2                                               |
| 5. Uçuş listeleri                                 | ⏳    | Faz 3                                               |
| 6. Yaka kartı (tekli/toplu PDF, logo)             | ⏳    | Faz 3 (logo alanı hazır)                            |
| 7. Tur, tarih, grup, yolcu atama, rehber          | ✅    | Turlar                                              |
| 7. Grup bazında oda/otobüs/uçuş organizasyonu     | ⏳    | Faz 2–3                                             |
| 8. Özet sayılar (yolcu, alacak)                   | ✅    | Ana panel, tur ve tahsilat                          |
| 8. Excel / PDF çıktıları                          | 🟡    | Yolcu, ödeme, tahsilat ✅; otel/otobüs/uçuş Faz 2–3 |

**Raporlar `app/Reports` içinde**: her rapor bir `Report` nesnesi; aynı nesne Excel ve PDF'e dönüşür, her indirme audit log'a yazılır. Faz 2–3 listeleri (oda, otobüs, uçuş, yaka kartı) bu altyapıyı kullanacak.

**İş kuralları `app/Actions` içinde** (kayıt, iptal, kapasite, grup silme, ödeme, taksit). Ekranlar ve Faz 4 API'si aynı sınıfları kullanır.
**Rehber ekranı yapıldı:** rehber sadece kendi grubunun yolcularını görür; fiyat, ödeme ve kimlik bilgisi sunucudan hiç gönderilmez (testlerle korunur).

## Alınan kararlar

- Yığın: Laravel 13 + Vue 3 + Inertia 3 + PostgreSQL 16, Docker.
- **Tur > Grup > Kayıt**: bir turda birden çok grup olabilir; her grubun rehberi var.
- Yolcu fotoğrafları şimdilik sunucu diskinde (`MEDIA_DISK=local`), ileride S3.
- Taksit takibi MVP'de (Faz 1, 3. adım).
- **Tema seçici** (kullanıcı fikri): Haremeyn (varsayılan), Sade kurumsal, Kum ve bakır + açık/koyu mod; kullanıcıya kaydedilir. Durum renkleri her temada aynı. PDF/Excel temadan bağımsız.
- Kendi hesabını silme kaldırıldı; hesapları yönetici "pasif yap" ile yönetir.
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
- [ ] **E-posta gönderimi** (şifre sıfırlama vb.): şu an `MAIL_MAILER=log`; Resend / Mailgun / SMTP bağlanmalı.
- [ ] Staging demo hesapları (`platform@`, `admin@`, `operasyon@`) staging'e Adım 5'ten önce yüklendi;
      `rehber@` hesabı ve hazır taksit planları staging'de yok (sadece lokalde).

## Sıradaki iş (önerilen sıra)

1. **Production hazırlığı (teknik)**: kalıcı disk → otomatik DB yedeği + geri yükleme denemesi →
   e-posta → `marhal-db-production` deploy → `main` dalına merge → production ortam değişkenleri
   (yeni anahtarlar, `.env.production`, git dışı) → Dokploy paneline HTTPS.
2. **KVKK görüşü** (müşteri tarafı) gelince production'a gerçek veri.
3. **Faz 2**: `hotels` + `tour_hotels` (Mekke/Medine), oda yerleşimi (`rooms`, `room_assignments`;
   cinsiyet / aile kuralı `person_relations` ile), otobüs ve koltuk planı (`buses`, `seat_assignments`),
   her biri için Excel/PDF (mevcut `app/Reports` altyapısı). Özellik bayrakları hazır:
   `room_planning`, `bus_planning`.
4. **Faz 3**: uçuş listeleri (`flight_lists`), yaka kartı (`badge_generation`; logo + fotoğraf hazır).
5. **Faz 4**: `/api/v1` (iş kuralları `app/Actions` içinde hazır), WhatsApp/SMS, muhasebe, vize takibi.

## Değerlendirme: iyi gidenler ve iyileştirilebilecekler (2026-10-04)

**İyi gidenler**

- Müşterinin ilk listesindeki 8 modülden Yolcu, Ödeme, Grup, Raporlama MVP'de çalışıyor; kalanların veri temeli hazır.
- Güvenlik baştan tasarlandı: acente izolasyonu, şifreli kimlik/pasaport + aranabilir hash, rol yetkileri,
  rehbere para/kimlik verisi hiç gönderilmiyor, audit log, ilk girişte şifre değişimi. 141 test bunları koruyor.
- İş kuralları `app/Actions`, raporlar `app/Reports` içinde → API ve yeni listeler kolay eklenir.

**Geriye dönüp bakınca daha iyi yapılabilecekler**

- Windows'ta Docker yavaşlığı (vendor volume) ve Dokploy ayarları (Nixpacks seçili, DB başlatılmamış)
  daha erken kontrol edilebilirdi; ilk deploy'da 2 tur kaybedildi.
- İş kuralları ilk 2 adımda controller'daydı; Adım 3'te Actions'a taşındı (önce başlanabilirdi).
- Bazı hatalar test yerine gözle bulundu (ayarlar sayfasında menü, rehbere fiyat sızması). Hepsine test
  eklendi ama **tarayıcı tabanlı uçtan uca testler** (Playwright/Dusk) yok.
- Toplu metin değişikliği betikle yapıldı ve bir kod tanımlayıcısını bozdu (tip kontrolü yakaladı).
  Sonraki çevirilerde betik yerine dosya dosya düzenleme tercih edilmeli.

**Teknik borç / iyileştirme listesi**

- [ ] Testler SQLite'ta koşuyor, production PostgreSQL → CI'da Postgres ile de koşulmalı
      (`.github/workflows/tests.yml` sadece `main` push'unda çalışıyor; `staging` için de açılmalı).
- [ ] Ölçek: tur detayı ve tahsilat ekranı tüm kayıtları belleğe alıp PHP'de hesaplıyor. Yüzlerce yolcuda
      sorun yok; binlerce yolculu acentede sayfalama + SQL'de toplama gerekir.
- [ ] Audit log yazılıyor ama **görüntüleme ekranı yok** (yönetici "kim neye baktı" görebilmeli).
- [ ] Platform: paket fiyat/limit düzenleme ekranı yok; abonelik ödemesi (iyzico/PayTR) yok.
- [ ] Rehber ekranı telefonda tablo; mobil için kart görünümü daha iyi olur.
- [ ] Arayüz metinleri Vue dosyalarında sabit Türkçe; çok dilli olacaksa çeviri dosyalarına taşınmalı.
- [ ] Ana panel sade; grafik (aylık tahsilat, tur doluluk) eklenebilir.
- [ ] Yolcu listesinde Excel'den toplu yolcu içe aktarma yok (acenteler için büyük kolaylık olur).
- [ ] Erişilebilirlik (klavye, ekran okuyucu) denetlenmedi.

## Çalışma notları (Claude Code / geliştirici için)

- Bu bilgisayarda PHP yok; her şey Docker içinde (`docker compose exec app ...`). Docker Desktop açık olmalı.
- `vendor` ve `node_modules` Docker volume'unda (Windows dosya paylaşımı yavaş); OPcache açık.
- Testler: `docker compose exec app php artisan test` · Analiz: `vendor/bin/phpstan analyse --memory-limit=1G`
  · Biçim: `vendor/bin/pint` ve `npm run check:fix` · Tip: `npm run types:check`
- Yeni Vue sayfası / rota ekledikten sonra: `php artisan wayfinder:generate --with-form` ve testlerden önce `npm run build`.
- **Staging'de DB var: eski migration dosyalarını değiştirme, her şema değişikliği yeni migration.**
- `git push`'u PowerShell'den yap (Bash'ten kimlik sorup takılabiliyor). Commit mesajı için dosya kullan (`git commit -F`).
- PHP dosyalarını sed/node ile düzenleme: ters bölü (`\`) kaçışları bozuluyor; doğrudan dosya düzenle.
- Arayüzde durum renkleri için `text-success` / `text-warning` / `text-danger` ve Badge `success|warning|danger`
  varyantlarını kullan; sabit renk (emerald, amber) yazma (temalar bozulur).
- Dokploy'da gizli anahtar / şifre alanlarını kullanıcı kendisi doldurur (asistan uzak formlara sır yazmaz).
- Kullanıcı Türkçe, teknik olmayan dille, adım adım ve "kontrollü" ilerlemek istiyor: her adım sonunda
  gereksinim tablosunu güncelle, sapmaları dürüstçe raporla, tarayıcıda gözle kontrol et.
