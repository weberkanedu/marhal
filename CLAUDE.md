# Marhal — Claude Code talimatları

Umre / Hac acenteleri için çok kiracılı SaaS (Laravel 13 + Vue 3 + Inertia 3 + PostgreSQL, Docker).

**Her oturumun başında önce [DURUM.md](DURUM.md)'yi oku**: nerede kaldığımız, kararlar, ortamlar,
açık işler, teknik borç ve çalışma notları oradadır. Ürün tanımı [PROJECT.md](PROJECT.md),
teknik şartname [SPEC.md](SPEC.md), yayın kontrol listesi [DEPLOY.md](DEPLOY.md),
Faz 2 tasarımı ve açık sorular [FAZ2.md](FAZ2.md), ileride yapılacak fikirler [FIKIRLER.md](FIKIRLER.md).

## Kurallar (kısa)

- Kullanıcıyla **Türkçe**, teknik olmayan dille konuş; adım adım ve kontrollü ilerle.
- Her iş bitince: testler (SQLite ve `composer test:pgsql`) , PHPStan, `npm run check`,
  `npm run types:check`; ekranları tarayıcıda gözle kontrol et; DURUM.md'yi güncelle.
- Acente izolasyonu: acenteye ait her model `BelongsToTenant` kullanır; başka acentenin kaydı 404 olmalı (test yaz).
- İş kuralları `app/Actions`, raporlar `app/Reports` (Report → Excel/PDF) içinde.
- Staging DB'si canlı: **eski migration'ları değiştirme**, yeni migration ekle.
- Gizli bilgiler (`.env*`) asla commit edilmez.
- Commit/push: `staging` dalı → Dokploy otomatik yayın. `main` = production (henüz açılmadı).

## Güncellemeye açık yapı (kullanıcının açık isteği)

Acenteler sürekli değişiklik isteyecek; her yeni iş bu kalıplara uymalı:

- **Kural tek yerde**: iş kuralı yalnızca `app/Actions` içinde; ekran, API ve demo verisi aynı sınıfı çağırır.
- **Liste / çıktı tek yerde**: her Excel/PDF bir `app/Reports/Definitions` sınıfı; sütun eklemek = tek dosya.
- **Paket bazlı açma-kapama**: yeni modül yeni bir `Feature` bayrağıyla gelir; paket matrisi kodsuz değişir.
- **Sabit değer yerine tanımlanabilir veri**: acenteye göre değişen şeyler (araç tipi, otel, yakınlık türü…)
  kodda değil, acentenin yönettiği tablolarda tutulur. Seçenek listeleri enum `options()` ile tek kaynaktan gelir.
- **Veri bozulmasın**: şablondan üretilen kayıt şablonun kopyasını saklar (ör. otobüs koltuk düzeni);
  şema değişikliği her zaman yeni migration, eski veri için gerekirse düzeltme / demo paketi.
- **Her yeni özelliğe**: test (izolasyon 404 dahil) + staging demo paketi + FAZ/DURUM notu.
