# Faz 3 — Uçuş listeleri ve yaka kartı

Durum: **Tamamlandı (2026-10-04).** Müşteri listesindeki karşılığı: Modül 5 (uçuş listeleri),
Modül 6 (yaka kartı), Modül 7 (uçuş organizasyonu), Modül 8 (uçuş listesi çıktıları).

Müşteriye ayrıca soru sorulmadan, sektörde yaygın uygulamaya göre **varsayılanlar** seçildi; hepsi sonradan
değiştirilebilir şekilde tasarlandı (CLAUDE.md "güncellemeye açık yapı"). Geri bildirim gelirse aşağıdaki
"Varsayılanlar" tablosu güncellenir.

## Adımlar

| #   | Adım                                                                                           | Durum |
| --- | ---------------------------------------------------------------------------------------------- | ----- |
| 1   | Uçuşlar: tura uçuş ekleme, yolcuları gruplarla / tek tek ekleme, PNR / bilet, havayolu listesi | ✅    |
| 2   | Yaka kartı: tekli / toplu PDF (logo, fotoğraf, grup, rehber, otel / oda, otobüs / koltuk)      | ✅    |
| 3   | Toparlama: rehber görünümü, telefon, demo verisi                                               | ✅    |

## Veri modeli (yeni migration'larla)

| Tablo               | Alanlar                                                                                                                                                                             |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `flights`           | id, tenant_id, tour_id, direction (`gidis` / `donus` / `aktarma`), airline, flight_no, departure_airport, arrival_airport, departure_at, arrival_at, pnr (grup PNR), baggage, notes |
| `flight_passengers` | id, tenant_id, flight_id, registration_id, pnr (kişisel, boşsa grup PNR'ı), ticket_no — unique: flight + registration                                                               |

## Kurallar

- Sadece turun aktif yolcusu eklenir; iptal edilen kayıt uçuşlardan çıkar.
- Aynı yolcu, saatleri çakışan iki uçuşta olamaz (engeller).
- **Uyarılar (engellemez)**: pasaport no yok; pasaport, dönüş tarihinden itibaren 6 aydan kısa geçerli.
- Unvan otomatik: uçuş tarihindeki yaşa göre INF (0–1), CHD (2–11), yetişkin MR / MRS.

## Varsayılanlar (müşteri isterse değişir)

| Konu               | Varsayılan                                                                                                                                    |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Havayolu listesi   | Sıra, Unvan, Soyad, Ad (pasaportta yazdığı gibi BÜYÜK harf), Cinsiyet, Doğum, Uyruk, Pasaport No, Pasaport bitiş, PNR, Bilet No               |
| Yaka kartı boyutu  | A4'e 8 kart (≈ 9 × 6,5 cm), kesme çizgili; tek kişilik çıktı da aynı boyut                                                                    |
| Yaka kartı içeriği | Logo + acente adı / telefon, fotoğraf, ad soyad, grup, rehber adı / telefonu, Mekke / Medine otel + oda, otobüs + koltuk, acil durum telefonu |
| Paket              | Uçuş listesi: Profesyonel + Kurumsal (`flight_lists`); yaka kartı: Kurumsal (`badge_generation`)                                              |

## Uygulama notları (1. adım, 2026-10-04)

- Tur sayfasında "Uçuşlar": yön, havayolu, uçuş no, havalimanı kodları (öneri listesi), yerel saatler, grup PNR, bagaj.
  Yeni uçuşta öneri: ilki gidiş (tur başı, IST → JED), sonrakiler dönüş.
- Uçuş sayfası: gruplarla toplu / tek tek ekleme (çakışan uçuştaki ve iptal edilmiş yolcu eklenmez, nedeni gösterilir),
  satırda kişisel PNR ve bilet no (kutudan çıkınca kaydolur; PNR boşsa grup PNR'ı), pasaport uyarıları.
- Havayolu listesi (Excel/PDF): unvan, soyad/ad BÜYÜK harf, cinsiyet, doğum, uyruk, pasaport no + bitiş, PNR, bilet no, grup.
- Rehber uçuş sayfasında yalnız kendi grubunu, pasaport bilgisi olmadan görür; değiştiremez, listeyi indiremez.
- Kayıt iptal edilince uçuşlardan çıkar. Demo paketi: `faz3-ucuslar`.

## Uygulama notları (2. adım, 2026-10-04)

- Tur sayfasında "Yaka kartları (PDF)": bütün tur veya seçili grup; her yolcu satırında tek kişilik kart.
- Kart (90 × 64 mm, A4'e 8): üstte logo + acente adı / telefonu + tur ve tarihler; fotoğraf (yoksa baş harfler),
  ad soyad BÜYÜK harf, grup, rehber adı / telefonu, oteller (oda no varsa yanında; odası yoksa grubun oteli,
  istisna otelde kalan için o otel), otobüs + koltuk; altta "Kaybolursanız / acil durumda: acente telefonu".
- Kimlik / pasaport bilgisi karta yazılmaz. Fotoğraflar PDF'e küçültülerek (en fazla 320 px) gömülür.
- Sadece personel basar (rehber basamaz); iptal edilen yolcuya kart çıkmaz; her indirme audit log'a yazılır.
- Kurumsal paket özelliği; staging'deki demo acenteye `faz3-yaka-karti` paketiyle ayrıca açıldı.

## Uygulama notları (3. adım — toparlama) · Faz 3 tamamlandı

- Uçuş yolcu sayfası telefonda tablo yerine kart (unvan, ad, grup, maskeli pasaport, uyarılar, PNR / bilet kutuları).
- Rehber gözüyle kontrol edildi: tur sayfasında kendi grubunun oda / koltuğu, uçuşlar (yolcu sayısı kendi grubuyla),
  ücret / kimlik bilgisi yok (PageSmokeTest ile de korunuyor).
- Açık (müşteri geri bildirimine bağlı): havayolu listesi ve yaka kartı içeriği / tasarımı; kalıcı disk ayarlanmadan
  staging'de yüklenen fotoğraflar her yayında silinir (DURUM.md açık işler).
