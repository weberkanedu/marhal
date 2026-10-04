# Faz 2 — Otel, oda ve otobüs yerleşimi (tasarım taslağı)

Durum: **Kararlar alındı (2026-10-04), uygulama başladı.** Kararlar en altta.
Müşteri listesindeki karşılığı: Modül 1 (Mekke/Medine otel bilgisi), Modül 3 (oda yerleşimi),
Modül 4 (otobüs yerleşimi), Modül 7 (grup bazında oda/otobüs organizasyonu), Modül 8 (otel/oda/otobüs listeleri).

## Hazır olan temel

- `persons.gender` (oda kuralı için zorunlu alan) ve `person_relations` tablosu (aile odası için).
- `registrations.room_type` (2/3/4/5 kişilik; fiyatı belirleyen oda tipi).
- Özellik bayrakları: `room_planning`, `bus_planning` (Profesyonel ve Kurumsal pakette açık).
- Excel/PDF altyapısı (`app/Reports`), iş kuralı katmanı (`app/Actions`), rehber yetki modeli.

## Önerilen veri modeli (yeni migration'larla)

| Tablo              | Alanlar                                                                                                                                  | Not                                                                               |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `hotels`           | id, tenant_id, name, city (`mekke` / `medine` / `diger`), address, phone, stars, notes                                                   | ✅ Yapıldı. Acente geneli; turda kullanılan otel silinemez                        |
| `tour_hotels`      | id, tenant_id, tour_id, hotel_id, check_in, check_out, notes + `group_tour_hotel` (hangi gruplar)                                        | ✅ Yapıldı. Grup bazında; bir grup aynı gecelerde iki otelde olamaz               |
| `rooms`            | id, tenant_id, tour_hotel_id, floor, room_no, capacity, kind (`erkek` / `kadin` / `aile`), notes                                         | ✅ Yapıldı. Toplu ekleme (501–510)                                                |
| `room_assignments` | id, tenant_id, tour_hotel_id, room_id, registration_id                                                                                   | ✅ Yapıldı. unique: tour_hotel + registration; aynı gecelerde iki otel engellenir |
| `person_relations` | (mevcut) person_id, related_person_id, relation                                                                                          | Aile odası kuralı için                                                            |
| `buses`            | id, tenant_id, tour_id, group_id (nullable), bus_no, plate, layout (`2+2` / `2+1`), seat_count, driver_name, driver_phone, guide_user_id |                                                                                   |
| `seat_assignments` | id, tenant_id, bus_id, seat_no, registration_id                                                                                          | unique: bus + seat, bus + registration                                            |

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

## Müşteri kararları (2026-10-04)

| #   | Soru                         | Karar                                                                                                                            | Tasarıma etkisi                                                                                                                                                                                            |
| --- | ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Otel tur mu, grup mu?        | **Grup bazında.** Aynı turun grupları farklı otellerde kalabilir; ayrıca gruptaki bazı yolcular başka otelde kalmak isteyebilir. | Konaklama (`tour_hotels`) hangi gruplar için olduğunu tutar (`group_tour_hotel`). Yolcu istisnası: yolcu, aynı şehirdeki başka bir konaklamanın odasına elle yerleştirilebilir (o şehirde tek oda kuralı). |
| 2   | Farklı oda tipine yerleşim   | **Sadece uyarı**; kullanıcı duruma göre devam edebilir.                                                                          | Yerleştirme engellenmez; ekranda ve raporda "oda tipi farklı" uyarısı.                                                                                                                                     |
| 3   | Cinsiyet + aile kuralı       | Kural doğru; **yakınlık listesi genişlesin** (kayınvalide, gelin vb.).                                                           | `Relation` enum'una kayın / gelin / damat / torun / büyükanne-baba / amca-dayı-hala-teyze / yeğen eklenir. Yolcu sayfasına "Yakınlar" bölümü.                                                              |
| 4   | Otobüs ve koltuk düzeni      | **Araç tipleri tanımlanabilir olsun** (VIP turlar için az koltuklu araçlar).                                                     | Acente geneli `vehicle_types` (ad, düzen 2+2 / 2+1 / 1+1, sıra sayısı, arka sıra, koltuk sayısı). Otobüs bir araç tipinden oluşturulur.                                                                    |
| 5   | Elle mi, otomatik mi?        | **İkisi de**: isteyen otomatik dağıtır (sonra elle düzeltir), isteyen tamamen elle yapar.                                        | Tıkla-yerleştir + "Otomatik dağıt" (önizleme → onay).                                                                                                                                                      |
| 6   | Otel / transfer liste örneği | Paylaşılacak.                                                                                                                    | Örnek gelene kadar genel format; gelince birebir uyarlanır.                                                                                                                                                |
| 7   | Mekke / Medine planları      | **Ayrı ayrı** yapılıyor.                                                                                                         | Oda planı her konaklama (otel) için ayrı; "diğer otelin yerleşimini kopyala" yardımcısı.                                                                                                                   |

Not (soru 4): bir otobüste birden çok grup olabilir mi — cevapta açıkça yok; esnek tasarlanır
(otobüs tura bağlı, isteğe bağlı grup(lar)).

## Uygulama notları (2. adım, 2026-10-04)

- **Yakınlıklar** iki yönlü saklanır; ters yön cinsiyete göre bulunur (kayınvalide ↔ gelin/damat,
  amca/dayı ↔ yeğen …). "Diğer" dışındaki yakınlıklar aile odası için aile sayılır (hepsi mahrem).
- **Aile odası kuralı**: yeni gelen, odadakilerden en az biriyle aile bağıyla bağlı olmalı.
- **Uyarılar (engellemez)**: ödenen oda tipi ≠ oda büyüklüğü; grubu bu otelde olmayan yolcu (istisna).
- **Otomatik dağıt**: önizleme → onay. Aile kümeleri birlikte; karma aileler aile odasına; önce yarı dolu
  ve ödenen tiple aynı büyüklükteki odalar; boş odaların türü yerleşenlere göre ayarlanır.
  Elle yapılmış yerleşimlere dokunmaz; sığmayanları nedeniyle listeler.
- **İptal** edilen kaydın odaları boşalır; oda silinirse yolcular yerleşmemiş listesine döner.
- **Raporlar**: "Otel oda listesi" (oda, kat, tip, tür, sıra, soyad/ad BÜYÜK harf, cinsiyet, doğum, uyruk,
  pasaport no + bitiş, grup, not; başlıkta giriş/çıkış ve rehber) ve "Oda doluluk özeti"
  (dolu/boş yatak, uyarılar, yerleşmemişler). Müşteri örneği gelirse birebir uyarlanır.
- Sonraya bırakılan: "Mekke yerleşimini Medine'ye kopyala" yardımcısı (oda numaraları otelden otele değiştiği için
  oda arkadaşı gruplarını taşıyacak şekilde), sürükle-bırak.

## Uygulama notları (3. adım, 2026-10-04)

- **Araç tipleri** (acente geneli): sol/sağ koltuk (1–2), sıra sayısı, arka sıra, orta kapı sırası; hazır şablonlar
  (Standart 2+2, VIP 2+1, Midibüs, Sprinter) ve canlı önizleme. Numaralama önden arkaya, soldan sağa; kapı sırasının sağı boş.
- **Otobüs** tura eklenir; koltuk düzeni araç tipinden **kopyalanır** (tip sonradan değişse de bozulmaz).
  Bir otobüste birden çok grup olabilir. Rehber / görevli için koltuk ayrılabilir.
- **Kurallar**: olmayan / ayrılmış / dolu koltuk engellenir; yolcu turda tek koltukta (başka otobüse taşınabilir);
  iptalde koltuk boşalır. Yanında karşı cinsten akraba olmayan yolcu → sadece uyarı.
- **Otomatik dağıt**: 65+ yaş ve aileleri öne; aileler ikişer ikişer yan yana ve art arda sıralarda;
  tek yolcular yabancı karşı cinsin yanına düşmeyecek şekilde. Önizleme → onay.
- **Raporlar**: otobüs yolcu listesi (koltuk sırasıyla, Excel/PDF) ve otobüse asılacak koltuk planı PDF'i.
