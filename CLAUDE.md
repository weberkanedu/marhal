# Marhal — Claude Code talimatları

Umre / Hac acenteleri için çok kiracılı SaaS (Laravel 13 + Vue 3 + Inertia 3 + PostgreSQL, Docker).

**Her oturumun başında önce [DURUM.md](DURUM.md)'yi oku**: nerede kaldığımız, kararlar, ortamlar,
açık işler, teknik borç ve çalışma notları oradadır. Ürün tanımı [PROJECT.md](PROJECT.md),
teknik şartname [SPEC.md](SPEC.md), yayın kontrol listesi [DEPLOY.md](DEPLOY.md),
Faz 2 tasarımı ve açık sorular [FAZ2.md](FAZ2.md).

## Kurallar (kısa)

- Kullanıcıyla **Türkçe**, teknik olmayan dille konuş; adım adım ve kontrollü ilerle.
- Her iş bitince: testler (SQLite ve `composer test:pgsql`) , PHPStan, `npm run check`,
  `npm run types:check`; ekranları tarayıcıda gözle kontrol et; DURUM.md'yi güncelle.
- Acente izolasyonu: acenteye ait her model `BelongsToTenant` kullanır; başka acentenin kaydı 404 olmalı (test yaz).
- İş kuralları `app/Actions`, raporlar `app/Reports` (Report → Excel/PDF) içinde.
- Staging DB'si canlı: **eski migration'ları değiştirme**, yeni migration ekle.
- Gizli bilgiler (`.env*`) asla commit edilmez.
- Commit/push: `staging` dalı → Dokploy otomatik yayın. `main` = production (henüz açılmadı).
