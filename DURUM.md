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

Faz 2 (oda / otobüs) ✅ ve Faz 3 (uçuş / yaka kartı) ✅ tamamlandı (2026-10-04). Sonraki: Faz 4 API/entegrasyonlar.

**Faz 2 adımları** (tasarım ve müşteri kararları: [FAZ2.md](FAZ2.md))

| #   | Adım                                                                                      | Durum |
| --- | ----------------------------------------------------------------------------------------- | ----- |
| 1   | Oteller ekranı + tura grup bazında konaklama (Mekke / Medine)                             | ✅    |
| 2   | Yakınlıklar (genişletilmiş liste) + oda planı (kurallar, ekran, otomatik dağıt, raporlar) | ✅    |
| 3   | Araç tipleri + otobüs / koltuk planı                                                      | ✅    |
| 4   | Toparlama: tur listesinde oda/koltuk (rehber dahil), Mekke→Medine kopyala, telefon uyumu  | ✅    |

## Gereksinim takibi (müşterinin ilk listesi)

Her adım sonunda güncellenir. ✅ bitti · 🟡 kısmen · ⏳ planlı

| Modül / madde                                     | Durum | Nerede / ne zaman                                                             |
| ------------------------------------------------- | ----- | ----------------------------------------------------------------------------- |
| 1. Ad soyad, iletişim, TC/pasaport, doğum tarihi  | ✅    | Yolcular                                                                      |
| 1. Kişi fotoğrafı, acil durum bilgisi             | ✅    | Yolcular                                                                      |
| 1. Kayıt ve grup bilgileri, yolcu durum takibi    | ✅    | Tur detayı (kayıt durumu)                                                     |
| 1. Mekke / Medine otel bilgileri                  | ✅    | Oteller + tur sayfası "Konaklama" (grup bazında)                              |
| 2. Toplam ücret, kalan bakiye                     | ✅    | Tur detayı, yolcu detayı                                                      |
| 2. Ödeme girişi, yöntem, tarih, geçmiş            | ✅    | Kayıt ödeme sayfası                                                           |
| 2. Taksit takibi                                  | ✅    | Taksit planı + gecikme                                                        |
| 2. Borçlu / tamamlanan listeleri, tahsilat raporu | ✅    | Tahsilat ekranı + Excel/PDF                                                   |
| 3. Oda yerleşim planı (otel/kat/oda, boş/dolu)    | ✅    | Tur → Konaklama → Oda planı (+ otomatik dağıt)                                |
| 4. Otobüs yerleşim planı (koltuk, rehber)         | ✅    | Araç tipleri + Tur → Otobüsler → Koltuk planı                                 |
| 5. Uçuş listeleri                                 | ✅    | Tur → Uçuşlar → yolcular, PNR / bilet, havayolu listesi                       |
| 6. Yaka kartı (tekli/toplu PDF, logo)             | ✅    | Tur sayfası → Yaka kartları (tur / grup / tek yolcu)                          |
| 7. Tur, tarih, grup, yolcu atama, rehber          | ✅    | Turlar                                                                        |
| 7. Grup bazında oda/otobüs/uçuş organizasyonu     | ✅    | Oda, otobüs, uçuş grup bazında (+ yolcu istisnaları)                          |
| 8. Özet sayılar (yolcu, alacak)                   | ✅    | Ana panel, tur ve tahsilat                                                    |
| 8. Excel / PDF çıktıları                          | ✅    | Yolcu, ödeme, tahsilat, otel, doluluk, otobüs, koltuk planı, uçuş, yaka kartı |

**Raporlar `app/Reports` içinde**: her rapor bir `Report` nesnesi; aynı nesne Excel ve PDF'e dönüşür, her indirme audit log'a yazılır. Faz 2–3 listeleri (oda, otobüs, uçuş, yaka kartı) bu altyapıyı kullanacak.

**İş kuralları `app/Actions` içinde** (kayıt, iptal, kapasite, grup silme, ödeme, taksit). Ekranlar ve Faz 4 API'si aynı sınıfları kullanır.
**Rehber ekranı yapıldı:** rehber sadece kendi grubunun yolcularını görür; fiyat, ödeme ve kimlik bilgisi sunucudan hiç gönderilmez (testlerle korunur).

## Alınan kararlar

- Yığın: Laravel 13 + Vue 3 + Inertia 3 + PostgreSQL 16, Docker.
- **Tur > Grup > Kayıt**: bir turda birden çok grup olabilir; her grubun rehberi var.
- Yolcu fotoğrafları şimdilik sunucu diskinde (`MEDIA_DISK=local`), ileride S3.
- Taksit takibi MVP'de (Faz 1, 3. adım).
- **Temalar (tasarım yenileme, 2026-10-05):** Gece Zümrüdü (koyu, varsayılan) ve Şafak (açık). Eski üç tema ve açık/koyu düğmesi kaldırıldı;
  eski seçimler migration ile taşındı (Haremeyn / Kum → Zümrüt, Kurumsal → Şafak). Ortak görünüm katmanı `resources/css/app.css`:
  arka plan ışıkları (`AppBackdrop`), cam kartlar, fareyi izleyen altın kenar (`useTheme.ts → initializeCardGlow`), `card-glow`,
  altın / koyu hap düğmeler, başlık ışıltısı, `ProgressBar`. Bileşenler `data-slot` ile seçilir; ekranlara sabit renk yazılmaz
  (`success|warning|danger|women|men` adları). PDF/Excel/yaka kartı temadan bağımsız.
- **Ekran düzeni (2026-10-04, kullanıcı onayı)**: sol menüde sadece günlük işler (Ana Panel, Turlar, Yolcular, Tahsilat)
    - "Acente ayarları". Ayarlar sekmeli tek sayfa (`layouts/agency`, `lib/agencySettings.ts`): Acente bilgileri, Personel,
      Oteller, Araç tipleri, Erişim kayıtları (operasyon yalnız Oteller / Araç tipleri). Kişisel ayarlar sağ alttaki kişi menüsünde.
      Tur sayfası sekmeli: Yolcular / Konaklama / Ulaşım / Çıktılar (bütün Excel/PDF); sekme adreste `?tab=`.
      Yeni ayar sayfası eklenecekse `agencySettingsTabs` + `agencySettingsPages`'e eklenir.
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

## Ertelenen altyapı işleri (kullanıcı kararı 2026-10-04: "sonra yapılacak")

Sıra ve anlatım kullanıcıyla konuşuldu; başlarken bu sırayla, adım adım yönlendir (gizli bilgileri kullanıcı girer):

1. **Kalıcı disk (staging)**: Dokploy → marhal-staging → Advanced → Volumes → _Volume Mount_ (bind değil),
   ad `marhal-staging-storage`, yol `/var/www/html/storage/app` → Deploy → fotoğraf yükle, tekrar deploy, fotoğraf duruyor mu.
2. **E-posta**: servis seçimi (öneri Brevo — AB, günde 300 ücretsiz — veya Resend), gönderen alan adı DNS doğrulaması
   (şimdilik `noreply@erkanicil.me`), Dokploy ortam değişkenleri (MAIL_*), "Şifremi unuttum" ile deneme.
3. **Yedek**: uzak depo (Hetzner Object Storage veya Cloudflare R2) → Dokploy S3 Destination → `marhal-db-staging`
   günlük yedek (03:00, 14 gün) → boş veritabanına **geri yükleme denemesi**.
4. **Production en son**: staging onaylanınca + KVKK görüşü gelince (DEPLOY.md). O zamana kadar staging'e de gerçek yolcu verisi girilmez.

## Kullanılabilirlik planı (kullanıcıyla konuşuldu, 2026-10-04)

1. ✅ Menü sadeleştirme + Acente ayarları sekmeli + tur sayfası sekmeleri (Çıktılar dahil).
2. ✅ Ana panel (`TourReadiness`): "bugün neyle ilgilenmeliyim" — vadesi geçen / yaklaşan taksitler, pasaportu eksik / 6 aydan kısa,
   yaklaşan turda odası / koltuğu / uçuşu olmayanlar, kontenjan.
   2b. ✅ Ana panel 2. tur (X'teki SaaS tasarımından uyarlanan fikirler): düğmeli + etiketli uyarılar (45 gün içindeki
   turlarda oda / koltuk / uçuş / ön kayıt eksikleri dahil), "Son hareketler" (audit_logs — **acente elle filtrelenir**,
   AuditLog global scope kullanmaz), "Turların durumu" tablosu (tahsilat %, oda, koltuk, uçuş, pasaport, Hazır / Eksik var),
   aylık tahsilat grafiği (son 6 ay, para birimi başına). İkinci kenar listesi ve yeni tema şimdilik yapılmadı (isteğe bağlı).
3. ✅ Excel'den toplu yolcu aktarma (Yolcular → "Excel'den aktar"): şablon indir → yükle → satır satır önizleme
   (yeni / zaten kayıtlı / hatalı, kimlik maskeli) → onay; istenirse aynı anda tura + gruba kayıt. Önizleme verisi
   şifreli önbellekte 30 dk, kullanıcıya özel. Başlık eş anlamlıları ve Excel biçim düzeltmeleri `PersonRowNormalizer`.
4. ⏳ Üst çubukta hızlı arama (ad / T.C. / pasaport) ve ilk kullanım rehberi (logo, otel, ilk tur).
5. ⏳ Yolcu sayfasında tur kayıtlarında oda / koltuk / uçuş bilgisi.

## Tasarım yenileme (dal `tasarim-yenileme`, kullanıcı onayıyla adım adım)

Kaynak: tasarım sayfası (claude.ai artifact UuhFHJG8dJ3nmbiUuDS1AS) + paketler sayfası (5Mxqaa5YmxceQ3qi7xzPCq, en sona ayrı plan).
Kural: her adım onaylanınca yazılır; dış servis gerektirenlerde önce seçenek + maliyet sorulur; staging'e push öncesi sorulur.
Yerleşim bilgisi (otel / oda / koltuk) yaka kartı, tur tablosu ve aile ekranına **tek kaynaktan** (`App\Support\Placements`) gider.

1. ✅ İki tema (Gece Zümrüdü, Şafak) + ortak görünüm katmanı. Migration: `users.theme` değerleri taşındı. Demo paketi gerekmedi (veri yok).
2. ✅ Ekranlar (2026-10-05). Ortak parçalar: `ExportMenu` ("Çıktı al": her çıktı Excel + PDF), `ProgressRing`, `kpi-tile`, `PersonAvatar`.
    - **Tur**: yolculuk çizelgesi `App\Support\Tours\TourJourney` (Hazırlık → gidiş → şehir konaklamaları → dönüş; aynı şehirdeki
      grup otelleri tek adım; uçuş / konaklama yoksa tur tarihleri) — **tek kaynak**: tur sayfası, "Tur programı" çıktısı, ileride aile ekranı.
      Halkalar (Kayıt, Tahsilat, Oda, Koltuk, Uçuş) ana paneldeki `TourReadiness` ile aynı hesap; rehbere gösterilmez.
      "WhatsApp grubu" davet bağlantısını kopyalar (`tours.whatsapp_link`, yalnız https://chat.whatsapp.com/).
    - **Yolcular**: sayı kartları = süzgeçler (`App\Support\Persons\PersonListFilter`: pasaport sorunlu / aktif turda / KVKK yok);
      pasaport kuralı tek yerde (`Person::passportIssue()` + `withPassportIssue()`); listede telefon da maskeli (`masked_phone`).
      Çıktılar: Yolcu listesi, Pasaport kontrol listesi (`PersonList`). "Yeni yolcu": elle / Excel / ön kayıt linki (7. adımda).
    - **Tahsilat**: özet `App\Support\Collections\CollectionSummary` (ana panel de bunu kullanır): bu ay tahsil edilen (geçen aya göre %),
      bu ay vadesi gelen taksit, gecikmiş, kalan. "Ödeme al": satırdan ya da üstten yolcu seçerek (`PaymentDialog` ortak).
      **Aylık hedef girilmedi** (tablo eklenmedi): hedef yerine "bu ay vadesi gelen" ile karşılaştırılıyor — kullanıcıya soruldu.
    - **Ana Panel**: selam + "Yeni kayıt" menüsü + "Sıradaki tur" kartı (geri sayım, hazırlık halkası, oda/koltuk/uçuş/tahsilat çubukları).
    - **Görüşünü paylaş** (menü altı): `feedback` tablosu, takip no = id; platform yöneticisi "Geri bildirimler"de listeler (yanıt: paketler aşaması).
      Migration: `tours.whatsapp_link`, `feedback`. Demo paketi `tasarim-2-ekranlar`.
3. ✅ Araç ve uçak tipleri + sürükle-bırak (2026-10-05).
    - **Araç**: `vehicle_types` / `buses` → `body` (otobüs / midibüs / minibüs / van, `VehicleBody`) ve `front_seats` (şoför yanı;
      varsa ilk numaralar onların). Otobüs düzeni tipten **kopyalanır** (eski araçlar etkilenmez). `BusLayout::frontZone()` = şoför yanı +
      ilk 3 sıra ("ön bölge"; 4. adımda hareket güçlüğü uyarısı buna bakacak). Koltuk planı: aile kümeli havuz, sürükle-bırak (dolu koltuğa
      bırakınca yer değiştirir, havuza bırakınca kalkar), tıklayarak yerleştirme (telefon / klavye), "Temizle", "Otomatik yerleştir".
      Çıktılar: Koltuk planı, Araç yolcu listesi, yeni **Şoför listesi** (`BusDriverList`). Ekrandaki önizleme `types/bus.ts → busGrid`
      sunucudaki `BusLayout::grid` ile aynı kural (ikisi birlikte değişmeli).
    - **Uçak**: `aircraft_types` (acentenin; hazır tipler `AircraftPresets`: A321neo, B737-800, A330-300, B777-300ER, B787-9),
      uçuşa kopyalanan kabin (`flights.cabin/first_row/last_row/exit_rows/blocked_seats`), `flight_passengers.seat_no`.
      Kurallar `App\Actions\Flights\FlightSeats` (atama, yer değiştirme, gri = başka yolcuya ait koltuk, uyarılar: acil çıkışta
      15 yaş altı / 65 yaş ve üstü, yanında akrabası olmayan karşı cins). Ekran: Uçuş → "Koltuk planı"; Acente ayarları → Uçak tipleri.
      Çıktı: **Koltuk tercih listesi** (`FlightSeatPreferences`) — havayoluna gönderilir, kesin koltuğu havayolu verir.
      Uçakta otomatik yerleştirme yok (havayolu tercihi; istenirse eklenir).
      Migration: `add_body_and_front_seats_to_vehicles`, `create_aircraft_types_and_flight_seats`. Demo paketi `tasarim-3-arac-ucak`.
4. ✅ İhtiyaç profili + otel kat planı (2026-10-05). **Kullanıcı kararları**: ihtiyaçları personel notlarıyla görür, rehber
   yalnız kendi grubunda adını; ayrı açık rıza (onay + tarih + giren kişi), rıza geri alınınca ihtiyaçlar silinir;
   türler: hareket, sağlık, beslenme, diğer (acente ekleyip kapatabilir — Acente ayarları → İhtiyaç türleri).
    - Veri: `need_types` (acenteye kopyalanan varsayılanlar `DefaultNeedTypes`; yeni acentede `CreateTenant` ekler),
      `person_needs` (kişi başına tek satır, hangi ihtiyaç + not **birlikte şifreli** `encrypted:array`; erişim kaydına içerik yazılmaz),
      `persons.health_consent_at/_by`. Okumanın tek yolu `App\Support\Needs\NeedProfiles`; kurallar `NeedEffect`e bakar.
    - Kurallar: araçta hareket güçlüğü → ön bölge (otomatik yerleştirmede öne, değilse uyarı); uçakta acil çıkış uyarısı;
      otelde asansöre yakın oda (otomatik dağıtmada önce ve oraya; uzak odada uyarı). "Karışık oda" zaten engelleniyor (AssignRoom).
    - Ekran: yolcu sayfası "İhtiyaçlar" kartı, Yolcular "Özel ihtiyaç" süzgeci, tur / oda / koltuk ekranlarında ihtiyaç adları.
    - Otel: `hotels.floors_count`, `tour_hotels.used_floors` (plandaki `hotel_floors` tablosu yerine konaklamada liste — her tur
      farklı kat alabilir), `rooms.near_elevator`. Oda planında kat kulesi + "Oteli tanımla"; oda eklerken "ilk N oda asansöre yakın".
    - Çıktılar: uçuş **Özel yardım listesi** (WCHR/WCHS/DEAF/BLND/SPML), otel **Kat planı** ve **İhtiyaç listesi** (sağlık verisi: personel).
      Migration: `create_needs_tables`, `add_floors_and_elevator_to_hotels`. Demo paketi `tasarim-4-ihtiyac-kat`.
      Not: SQLite tırnaklı bilinmeyen sütunu metin sayıyor — sütun adı içeren testler mutlaka `composer test:pgsql` ile de koşmalı.
5. ✅ Yaka kartı yenileme (2026-10-05). Acente ayarları → **Yaka kartı** (`badge_settings`, acente başına tek satır; yoksa
   varsayılan): boy (`BadgeSize`: dikey A6 2×2, yatay 9×6,4 2×4, plastik 8,6×5,4 2×5), ön yüz alanları (fotoğraf, oteller+oda,
   otobüs+koltuk, rehber, QR), arka yüz (Türkçe / İngilizce / Arapça "Kaybolursanız", acil telefon, otel adresleri — dikeyde),
   sağlık notu (yalnız acente açtıysa **ve** yolcunun sağlık rızası varsa). Seri no = kayıt id'sinin son 6 hanesi.
   Çift taraflı baskı: arka sayfada sütunlar ters (uzun kenardan çevirince doğru karta denk gelir).
   - **Tek kaynak**: otel / oda / koltuk artık `Placements::hotels()` ve `Placements::seat()`ten (eskiden kartın kendi hesabı vardı);
     aile ekranı da bunu kullanacak.
   - **Grup rengi** `groups.color` (palet `GroupColors::PALETTE`, seçilmezse turdaki sıraya göre); kart bandı ve otobüs tabelası
     (koltuk planı PDF'inin üstündeki renkli grup şeridi).
   - **Arapça**: dompdf harf birleştirmez / sağdan sola dizmez → `App\Support\ArabicText` (Presentation Forms-B, DejaVu Sans).
     Yalnız kısa sabit cümleler için; PDF çıktısı gözle kontrol edildi.
   - QR: bacon/bacon-qr-code (Fortify'dan zaten vardı), içerik: yolcu, acente, acil telefon, kart no (aile ekranı bağlantısı 7. adımda).
   Migration: `create_badge_settings_and_group_colors`. Demo paketi `tasarim-5-yaka-karti`.
6. ⏳ Hazırlık takibi (`readiness_items`, `readiness_checks`).
7. ⏳ Aile ekranı + fotoğrafla ön kayıt (MRZ okuma servisi için önce seçenek / maliyet).
8. ⏳ WhatsApp tahsilat asistanı (önce sağlayıcı + sanal POS seçenekleri / maliyet).
9. ⏳ Faz 4 entegrasyonlar + NFC (`tenant_integrations`, acente ayarından aç/kapa; önce seçenek / maliyet).
   Son: paketler, hesap paylaşımı koruması, iyzico abonelik, platform paneli (ayrı plan).

## Sıradaki iş (önerilen sıra)

1. **Production hazırlığı (teknik)** — adım adım liste: [DEPLOY.md](DEPLOY.md): kalıcı disk → otomatik DB yedeği + geri yükleme denemesi →
   e-posta → `marhal-db-production` deploy → `main` dalına merge → production ortam değişkenleri
   (yeni anahtarlar, `.env.production`, git dışı) → Dokploy paneline HTTPS.
2. **KVKK görüşü** (müşteri tarafı) gelince production'a gerçek veri.
3. **Faz 2** (müşteri kararları alındı, [FAZ2.md](FAZ2.md)): ✅ 1. adım oteller + konaklama,
   ✅ 2. adım yakınlıklar + oda planı + otel oda listesi / doluluk raporları.
   ✅ 3. adım araç tipleri + otobüs / koltuk planı + otobüs listesi ve koltuk planı PDF'i.
   ✅ 4. adım toparlama: tur listesi + yolcu raporunda oda / koltuk (rehber de görür), "Başka otelden kopyala", telefon düzeltmeleri. **Faz 2 tamamlandı.**
   Otel listesi formatı: müşteri örneği yok; genel format yapıldı, geri bildirime göre güncellenecek.
4. **Faz 3** ([FAZ3.md](FAZ3.md)): ✅ uçuşlar + havayolu listesi, ✅ yaka kartı, ✅ toparlama. **Faz 3 tamamlandı.**
   Müşterinin ilk listesindeki 8 modülün hepsi çalışıyor; sıradaki büyük iş production hazırlığı (madde 1) ve Faz 4.
5. **Faz 4**: `/api/v1` (iş kuralları `app/Actions` içinde hazır), WhatsApp/SMS, muhasebe, vize takibi.
6. **Fikir havuzu** ([FIKIRLER.md](FIKIRLER.md)): pasaportu NFC ile okuyup taslak kayıt + onay (premium, üst paket;
   öneri: Faz 4 API'sinden sonra "Faz 5 — Mobil tarayıcı", önce kamerayla MRZ okuma ara çözümü).

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
- Bazı hatalar test yerine gözle bulundu. **Önlem:** `PageSmokeTest` tüm sayfaları her rolle otomatik gezer
  (500 yok, rehbere para/kimlik verisi yok, menü dolu). JavaScript çalıştıran uçtan uca test (Playwright) hâlâ yok.
- Toplu metin değişikliği betikle yapıldı ve bir kod tanımlayıcısını bozdu (tip kontrolü yakaladı).
  Sonraki çevirilerde betik yerine dosya dosya düzenleme tercih edilmeli.

**Teknik borç / iyileştirme listesi**

- [x] Testler PostgreSQL ile de koşuyor: CI (main + staging) Postgres kullanır; yerelde `composer test:pgsql`.
- [x] Tahsilat ekranı SQL'de hesaplar ve 50'lik sayfalar (`CollectionScaleTest`). Tur detayı hâlâ tüm kayıtları
      yükler (tur başına yüzlerce yolcu için yeterli).
- [x] Erişim kayıtları ekranı (yönetici, filtreli, sayfalı).
- [x] Platform paket düzenleme ekranı. - [ ] Abonelik ödemesi (iyzico/PayTR) yok.
- [x] Tur yolcu listesi telefonda kart görünümü.
- [ ] Arayüz metinleri Vue dosyalarında sabit Türkçe; çok dilli olacaksa çeviri dosyalarına taşınmalı.
- [ ] Ana panel sade; grafik (aylık tahsilat, tur doluluk) eklenebilir.
- [ ] Erişilebilirlik (klavye, ekran okuyucu) denetlenmedi.

## Çalışma notları (Claude Code / geliştirici için)

- Bu bilgisayarda PHP yok; her şey Docker içinde (`docker compose exec app ...`). Docker Desktop açık olmalı.
- `vendor` ve `node_modules` Docker volume'unda (Windows dosya paylaşımı yavaş); OPcache açık.
- Testler: `docker compose exec app php artisan test` · Analiz: `vendor/bin/phpstan analyse --memory-limit=1G`
  · Biçim: `vendor/bin/pint` ve `npm run check:fix` · Tip: `npm run types:check`
- Yeni Vue sayfası / rota ekledikten sonra: `php artisan wayfinder:generate --with-form` ve testlerden önce `npm run build`.
- **Staging'de DB var: eski migration dosyalarını değiştirme, her şema değişikliği yeni migration.**
- `git push`'u PowerShell'den, etkileşimsiz yap: `$env:GIT_TERMINAL_PROMPT='0'; $env:GCM_INTERACTIVE='never'; git push origin staging`
  (aksi halde kimlik yöneticisi görünmeyen bir seçim penceresi açıp takılabiliyor; kayıtlı GitHub kimliği çalışıyor).
  Commit mesajı için dosya kullan (`git commit -F`).
- PHP dosyalarını sed/node ile düzenleme: ters bölü (`\`) kaçışları bozuluyor; doğrudan dosya düzenle.
- Arayüzde durum renkleri için `text-success` / `text-warning` / `text-danger` ve Badge `success|warning|danger`
  varyantlarını kullan; sabit renk (emerald, amber) yazma (temalar bozulur).
- Dokploy'da gizli anahtar / şifre alanlarını kullanıcı kendisi doldurur (asistan uzak formlara sır yazmaz).
- **Demo veri paketleri (kullanıcı isteği):** her yeni özellik için `database/seeders/Demo/` altında bir paket yaz ve
  `UpdateDemoData::PACKS`'e ekle. Staging her açılışta `marhal:demo-data` çalıştırır (docker/entrypoint.d/60-demo-data.sh);
  paket demo acenteye bir kez yüklenir (`demo_packs`), kullanıcının silmeleri korunur. Production'da çalışmaz.
  Böylece kullanıcı yeni özelliği staging'de veri girmeden görür. Gerçek müşteriler boş başlar.
- Kullanıcı Türkçe, teknik olmayan dille, adım adım ve "kontrollü" ilerlemek istiyor: her adım sonunda
  gereksinim tablosunu güncelle, sapmaları dürüstçe raporla, tarayıcıda gözle kontrol et.
