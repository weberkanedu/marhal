# Marhal — Umre Organizasyon Yönetim Yazılımı — PROJECT.md

**Proje adı:** Marhal
**Repo:** https://github.com/weberkanedu/marhal.git

## 1. Amaç

Umre/Hac organizasyonu yapan acentelerin tüm süreçlerini (yolcu kaydı, ödeme takibi,
oda/otobüs/uçuş planlaması, yaka kartı üretimi, raporlama) tek bir sistemde yönetmesini
sağlayan, üyelik paketi üzerinden satılan bir SaaS platformu.

## 2. Hedef Kullanıcı

- Umre/Hac organizasyonu düzenleyen seyahat acenteleri
- Acente içinde farklı roller: yönetici, operasyon personeli, rehber/grup sorumlusu

## 3. İş Modeli

- Aylık/yıllık üyelik paketleri üzerinden satış (SaaS)
- Paket seviyeleri arasında özellik ve kullanım limiti farkı (bkz. SPEC.md §4)
- Her acente bağımsız bir "tenant" olarak sistemde izole çalışır

## 4. Kapsam (Modüller)

1. Yolcu / Kayıt Yönetimi
2. Ödeme ve Tahsilat Takibi
3. Oda Yerleşim Planı
4. Otobüs Yerleşim Planı
5. Uçak / Uçuş Listeleri
6. Otomatik Yaka Kartı Oluşturma
7. Grup ve Organizasyon Yönetimi
8. Raporlama (Excel/PDF)

## 5. MVP Kapsamı (İlk Faz)

Aşağıdakiler dışındaki tüm modüller sonraki fazlara bırakılacak:

- [ ] Tenant (acente) kaydı ve kullanıcı girişi
- [x] Yolcu kayıt yönetimi (CRUD, fotoğraf, kimlik doğrulama)
- [ ] Tur ve grup oluşturma, yolcuların tura/gruba kaydı
- [ ] Ödeme/tahsilat takibi (toplam ücret, alınan ödeme, kalan bakiye, taksit planı)
- [ ] Basit raporlama (yolcu listesi, ödeme durumu — Excel/PDF export)

## 6. Sonraki Fazlar

- Faz 2: Oda yerleşim planı, Otobüs yerleşim planı (grup bazında)
- Faz 3: Uçuş listeleri, Otomatik yaka kartı üretimi
- Faz 4: Gelişmiş raporlama, API erişimi, üçüncü parti entegrasyonlar
  (WhatsApp/SMS bildirim, muhasebe programı entegrasyonu, vize takibi vb.)

## 7. Mimari Prensipler

- **Multi-tenant**: Tek veritabanı, her tabloda `tenant_id` ile izolasyon (başlangıç için)
- **Monolit + Inertia**: Laravel ve Vue tek projede; arayüz Inertia ile sunulur. İş mantığı
  servis/aksiyon sınıflarında tutulur, böylece Faz 4'teki harici REST API aynı mantığı kullanır
- **Modüler**: Her modül (Yolcu, Ödeme, Oda, Otobüs, Uçuş, Yaka Kartı, Rapor) bağımsız
  geliştirilebilir/genişletilebilir olmalı
- **Feature-flag tabanlı paketleme**: Hangi tenant'ın hangi modüle erişimi olduğu
  veritabanı/konfigürasyon üzerinden yönetilir, kod değişikliği gerektirmez

## 8. Teknik Yığın

- Backend: Laravel 13 (PHP 8.4)
- Frontend: Vue 3 + Inertia.js 3 + TypeScript + Tailwind CSS 4
- Veritabanı: PostgreSQL (Dokploy üzerinden tek tıkla provizyon)
- Dosya/Fotoğraf depolama: S3-uyumlu object storage (ileride eklenecek — henüz yok)
- PDF/Excel üretimi: Sunucu taraflı (yaka kartı, liste çıktıları)
- Hosting: Hetzner, Dokploy ile yönetiliyor
- Deployment: Dokploy (Docker + Traefik tabanlı, GitHub push-to-deploy)
- CI/CD: Dokploy'un kendi auto-deploy mekanizması (branch bazlı)
- Hata izleme: Sentry

## 8a. Ortamlar ve Domain Yapısı

- **Production**: `app.erkanicil.me` ← `main` branch (Dokploy application #1)
- **Staging**: `staging.erkanicil.me` ← `staging` branch (Dokploy application #2)
- Geliştirme akışı: önce `staging` branch'e push → staging ortamında test →
  sorun yoksa `main`'e merge → production otomatik güncellenir
- Not: Domain şimdilik erkanicil.me altında; ileride Marhal'a özel bir domaine
  taşınması planlanıyor (altyapı Docker tabanlı olduğu için taşıma kolay olacak)

## 9. Hassas Veri / KVKK

- T.C. Kimlik No, pasaport bilgisi, kişi fotoğrafı → hassas veri; Umre/Hac katılımı dini
  inancı gösterebileceği için özel nitelikli veri olarak ele alınmalı (bkz. SPEC.md §6)
- Şifreleme (at-rest), erişim loglama, rol bazlı yetkilendirme zorunlu
- Hetzner (AB) barındırması KVKK md. 9 yurt dışına aktarım kapsamında → hukuki görüş alınmalı
- Veri sorumlusu/veri işleyen sözleşmesi (acente ↔ yazılım sağlayıcı) ayrıca hazırlanmalı

## 10. Durum

- [ ] Proje başlatıldı — tarih: _______
- [ ] MVP teslim tarihi hedefi: _______
