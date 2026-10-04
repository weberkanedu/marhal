# Marhal — Fikir havuzu (ileride yapılacaklar)

Kullanıcının istediği, henüz fazlara yerleştirilmemiş özellikler. Sırası gelince tasarım burada
olgunlaştırılır, onaylanınca bir faza alınır.

---

## 1. Pasaportu NFC ile okuyup hızlı yolcu kaydı (premium)

**Tarih:** 2026-10-04 · **Durum:** Not alındı, uygulanmadı · **Paket:** sadece üst paket(ler)
(yeni özellik bayrağı, örn. `passport_scan`; Başlangıç'ta kapalı)

### Kullanıcının isteği

Pasaportun çipi NFC ile taransın. Gelen bilgiler kayıt ekranına otomatik düşsün; personel kaydı kontrol
edip onaylasın. Böylece yolcu kaydı çok hızlı yapılsın. Arada bir "köprü" gerekiyorsa organizasyonu
biz kuralım.

### Gerçekleştirilebilirlik (araştırma notu)

- Biyometrik (e-)pasaportların çipi (ICAO 9303 standardı) okunabilir. Çipte: ad, soyad, cinsiyet, doğum tarihi,
  uyruk, pasaport no, bitiş tarihi (DG1 / MRZ) ve **vesikalık fotoğraf** (DG2) bulunur. Türk pasaportlarında
  MRZ'nin "kişisel numara" alanında genelde T.C. kimlik no da yer alır (uygulamada doğrulanacak).
- Çip kilitlidir: açmak için önce pasaportun alt kısmındaki **MRZ satırları kamerayla okunur** (pasaport no,
  doğum ve bitiş tarihinden erişim anahtarı üretilir), sonra telefon pasaportun kapağına yaklaştırılır.
- **Tarayıcıdan (web sitesinden) NFC ile pasaport okunamaz.** Web NFC yalnızca basit etiketleri okur;
  pasaport çipi için **telefon uygulaması** gerekir. Bu "köprü" = küçük bir mobil uygulama:
  - Android: açık kaynak JMRTD kütüphanesi ile mümkün (NFC'li telefonların çoğu).
  - iPhone: iOS 13+ Core NFC ile mümkün (iPhone 7 ve sonrası), uygulamada pasaport okuma izni tanımlanır.
  - Alternatif köprü: masaüstü USB pasaport okuyucu (büroda sabit cihaz). Pahalıdır, ikinci seçenek.
- Uygulama mağazası hesapları gerekir: Apple Developer (yıllık ~99 $), Google Play (tek sefer ~25 $).
  Alternatif olarak hazır ticari SDK'lar (lisans ücretli) değerlendirilebilir.

### Önerilen akış

1. Personel telefondaki **Marhal Tarayıcı** uygulamasına kendi Marhal hesabıyla giriş yapar (acenteye bağlı).
2. Kamera MRZ'yi okur → telefon pasaporta yaklaştırılır → çip okunur (birkaç saniye).
3. Veriler şifreli bağlantıyla Marhal'a **"Taslak kayıt"** olarak gönderilir (doğrudan yolcu oluşturulmaz).
4. Web'de yeni **"Taranan pasaportlar"** ekranı: taslaklar listelenir; personel bilgileri kontrol eder,
   eksikleri (telefon, acil durum kişisi, tur/grup, oda tipi) tamamlar ve **Onayla** der.
   - Aynı pasaport no / T.C. kimlik no ile kişi zaten varsa yeni kişi açılmaz; mevcut kişi güncellenir
     (farklar yan yana gösterilir).
   - İstenirse onay sırasında doğrudan bir tura / gruba kayıt da yapılır.
5. Reddedilen veya 7 gün içinde onaylanmayan taslaklar otomatik silinir.

**Ara çözüm (uygulama gerektirmez, daha hızlı yapılır):** web sayfasında telefonun kamerasıyla yalnızca MRZ'yi
okumak. Fotoğraf hariç aynı alanlar gelir; çip doğrulaması olmaz. Önce bu sürüm çıkarılıp NFC uygulaması
sonra eklenebilir.

### Teknik taslak (sırası gelince netleşir)

- Tablo `passport_scans`: tenant_id, scanned_by (user), durum (taslak / onaylandı / reddedildi), şifreli alanlar
  (pasaport no, T.C. no), ad/soyad/cinsiyet/doğum/uyruk/bitiş, fotoğraf yolu, çip doğrulandı mı,
  eşleşen person_id, onaylayan ve onay tarihi.
- Uygulamanın bağlandığı uç noktalar Faz 4'teki API altyapısını kullanır (kullanıcıya özel token, acente izolasyonu).
- Onay işlemi `app/Actions` içinde (mevcut yolcu oluşturma kurallarıyla aynı doğrulamalar).
- Fotoğraf mevcut yolcu fotoğrafı alanına (ve ileride yaka kartına) aktarılır.

### KVKK / güvenlik

- Çipteki fotoğraf biyometrik veri sayılabilir → **açık rıza** şart (mevcut `kvkk_consent_at` akışına bağlanır).
- Telefonda veri saklanmaz; gönderildikten sonra cihazdan silinir. Taslaklar şifreli tutulur, erişim audit log'a yazılır.
- Her tarama ve onay audit log'a düşer.

### Ön koşullar ve yeri

- Faz 4'teki API altyapısı (token ile giriş) önce yapılmalı.
- Öneri: Faz 4'ten sonra **Faz 5 — Mobil tarayıcı** olarak (önce MRZ ara çözümü, sonra NFC uygulaması).
