# Faz 2 — Otel, oda ve otobüs yerleşimi (tasarım taslağı)

Durum: **Hazırlık / onay bekliyor.** Aşağıdaki "Açık sorular" cevaplanınca uygulamaya geçilir.
Müşteri listesindeki karşılığı: Modül 1 (Mekke/Medine otel bilgisi), Modül 3 (oda yerleşimi),
Modül 4 (otobüs yerleşimi), Modül 7 (grup bazında oda/otobüs organizasyonu), Modül 8 (otel/oda/otobüs listeleri).

## Hazır olan temel

- `persons.gender` (oda kuralı için zorunlu alan) ve `person_relations` tablosu (aile odası için).
- `registrations.room_type` (2/3/4/5 kişilik; fiyatı belirleyen oda tipi).
- Özellik bayrakları: `room_planning`, `bus_planning` (Profesyonel ve Kurumsal pakette açık).
- Excel/PDF altyapısı (`app/Reports`), iş kuralı katmanı (`app/Actions`), rehber yetki modeli.

## Önerilen veri modeli (yeni migration'larla)

| Tablo              | Alanlar                                                                                                                                  | Not                                                                                  |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `hotels`           | id, tenant_id, name, city (`mekke` / `medine` / `diger`), address, phone, stars                                                          | Acente geneli; turlar arasında tekrar kullanılır                                     |
| `tour_hotels`      | id, tenant_id, tour_id, hotel_id, check_in, check_out, notes                                                                             | Turun hangi otelde kaç gece kalacağı (Mekke + Medine)                                |
| `rooms`            | id, tenant_id, tour_hotel_id, floor, room_no, capacity, gender (`erkek` / `kadin` / `aile`), notes                                       | Oda listesi tur-otel bazında                                                         |
| `room_assignments` | id, tenant_id, room_id, registration_id                                                                                                  | Kayıt başına her tur-otelde **en fazla bir** oda (unique: tour_hotel + registration) |
| `person_relations` | (mevcut) person_id, related_person_id, relation                                                                                          | Aile odası kuralı için                                                               |
| `buses`            | id, tenant_id, tour_id, group_id (nullable), bus_no, plate, layout (`2+2` / `2+1`), seat_count, driver_name, driver_phone, guide_user_id |                                                                                      |
| `seat_assignments` | id, tenant_id, bus_id, seat_no, registration_id                                                                                          | unique: bus + seat, bus + registration                                               |

## İş kuralları (app/Actions, testlerle korunacak)

- **Kapasite**: odaya kapasitesinden fazla kişi yerleşemez; otobüste koltuk sayısı aşılamaz.
- **Cinsiyet**: `erkek` / `kadin` odaya karşı cinsten kişi yerleşemez.
- **Aile odası**: `aile` odasındaki herkes birbirine `person_relations` ile bağlı olmalı (öneri; soru 3).
- **Tekillik**: bir yolcu aynı tur-otelde iki odada, aynı turda iki koltukta olamaz.
- **İptal**: kayıt iptal edilince oda ve koltuk yerleşimi otomatik boşaltılır.
- **Oda tipi uyumu**: 4 kişilik ücret ödeyen yolcunun 2 kişilik odaya yerleşmesi → uyarı (soru 2).
- Rehber: sadece kendi grubunun oda / koltuk listesini görür, değiştiremez.

## Ekranlar

1. **Oteller** (acente geneli liste) + tur sayfasında "Konaklama" sekmesi (Mekke / Medine otelleri, tarihler).
2. **Oda planı** (tur → otel): oda kartları ızgarası (dolu / boş, cinsiyet rengi), solda yerleşmemiş
   yolcular; tıkla-yerleştir (telefonda da çalışır) + **otomatik dağıt** yardımcısı (aileleri birlikte,
   cinsiyete göre, oda tipine göre) — sonuç önizlenir, onaylanınca kaydedilir.
3. **Otobüs planı** (tur → otobüs): koltuk düzeni çizimi (2+2 / 2+1), koltuğa tıkla-yerleştir,
   grup bazında otomatik dağıt.
4. Yolcu ve tur listelerinde oda / koltuk bilgisi sütunları.

## Raporlar (mevcut altyapıyla)

- Otel oda listesi (otele verilecek: oda no, kişiler, cinsiyet, pasaport no — yönetici tam / diğerleri maskeli)
- Oda doluluk özeti (boş yatak sayısı)
- Otobüs yolcu listesi (koltuk sırasıyla) — rehber için PDF
- Bu listeler Faz 3'teki **yaka kartı** için de veri kaynağı olur (otel adı, otobüs no, koltuk no).

## Tahmini sıra

1. Oteller + tur konaklaması (migration, model, ekran, test)
2. Oda planı (kurallar → ekran → otomatik dağıt → raporlar)
3. Otobüs planı (aynı yapı)
4. Rehber görünümü, telefon uyumu, PageSmokeTest kapsamı, DURUM.md güncellemesi

## Açık sorular (müşteriden cevap bekleniyor)

1. **Otel ataması tur bazında mı, grup bazında mı?** Aynı turdaki A ve B grupları farklı otellerde kalabilir mi?
2. **Oda tipi ve yerleşim**: Yolcunun ödediği oda tipi (ör. 4 kişilik) ile yerleştiği oda farklı olabilir mi?
   Sistem engellesin mi, sadece uyarsın mı?
3. **Karma oda kuralı**: Aynı odada sadece aynı cinsiyet + "aile" odası istisnası doğru mu?
   Aile sayılacak yakınlıklar: eş, anne, baba, çocuk, kardeş — başka (ör. kayınvalide, gelin) eklensin mi?
4. **Otobüs**: Her grubun kendi otobüsü mü olur, yoksa bir otobüste birden fazla grup olabilir mi?
   Kullanılan koltuk düzenleri (2+2, 46 koltuk; 2+1 VIP …)?
5. **Yerleşim nasıl yapılıyor**: Elle mi, yoksa "otomatik dağıt" + elle düzeltme mi tercih edilir?
6. **Otelin istediği liste formatı**: Otele / transfer firmasına verilen örnek bir liste (Excel) paylaşılabilir mi?
   Raporlar birebir o formatta üretilir.
7. **Medine ve Mekke oda planları** ayrı ayrı mı yapılıyor (genelde evet), yoksa aynı yerleşim mi taşınıyor?
