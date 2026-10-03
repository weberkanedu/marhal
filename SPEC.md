# Umre Organizasyon Yönetim Yazılımı — SPEC.md

## 1. Veri Modeli (Taslak)

### tenants (Acenteler)
| Alan | Tip | Açıklama |
|---|---|---|
| id | uuid | PK |
| name | string | Acente adı |
| plan_id | fk → plans | Üyelik paketi |
| created_at | timestamp | |

### users (Acente kullanıcıları)
| Alan | Tip | Açıklama |
|---|---|---|
| id | uuid | PK |
| tenant_id | fk → tenants | |
| email | string | |
| password_hash | string | |
| role | enum | admin / operasyon / rehber |

### groups (Turlar/Gruplar)
| Alan | Tip | Açıklama |
|---|---|---|
| id | uuid | PK |
| tenant_id | fk → tenants | |
| name | string | Örn: "Ekim 2026 Umre Turu" |
| start_date | date | |
| end_date | date | |
| guide_name | string | Rehber/grup sorumlusu |

### passengers (Yolcular)
| Alan | Tip | Açıklama |
|---|---|---|
| id | uuid | PK |
| tenant_id | fk → tenants | |
| group_id | fk → groups | |
| full_name | string | |
| national_id | string (encrypted) | T.C. Kimlik No |
| passport_no | string (encrypted) | |
| birth_date | date | |
| photo_url | string | Object storage URL |
| phone | string | |
| emergency_contact | string | |
| mecca_hotel_id | fk → hotels | |
| medina_hotel_id | fk → hotels | |
| status | enum | kayıtlı / onaylı / iptal |

### payments (Ödemeler)
| Alan | Tip | Açıklama |
|---|---|---|
| id | uuid | PK |
| tenant_id | fk → tenants | |
| passenger_id | fk → passengers | |
| total_amount | decimal | Toplam paket ücreti |
| paid_amount | decimal | Alınan ödeme (toplam) |
| installments | json/table | Taksit kayıtları |
| payment_method | enum | nakit / havale / kredi kartı |
| payment_date | timestamp | |

### hotels / rooms (Oteller / Odalar)
| Alan | Tip |
|---|---|
| hotel: id, tenant_id, city (Mekke/Medine), name | |
| room: id, hotel_id, floor, room_no, capacity | |
| room_assignment: id, room_id, passenger_id | |

### buses / seats (Otobüsler / Koltuklar)
| Alan | Tip |
|---|---|
| bus: id, tenant_id, bus_no, guide_name | |
| seat_assignment: id, bus_id, seat_no, passenger_id | |

### flights (Uçuşlar)
| Alan | Tip |
|---|---|
| id, tenant_id, airline, flight_no, direction (gidiş/dönüş), departure_time, arrival_time | |
| flight_assignment: id, flight_id, passenger_id | |

### plans / plan_features (Paket ve Özellik Matrisi)
| Alan | Tip |
|---|---|
| plan: id, name (Başlangıç/Profesyonel/Kurumsal), price, user_limit, group_limit | |
| plan_feature: id, plan_id, feature_key, enabled (bool) | |

## 2. Feature Flag Mekanizması
Her modül/özellik bir `feature_key` ile tanımlanır (örn. `room_planning`,
`bus_planning`, `flight_lists`, `badge_generation`, `advanced_reporting`, `api_access`).
Backend, her istek öncesi `tenant.plan.features[feature_key]` kontrolü yapar.
Yeni paket/özellik eklemek → veritabanına satır eklemek, kod değişikliği değil.

## 3. Başlangıç Paket Matrisi
| Özellik | Başlangıç | Profesyonel | Kurumsal |
|---|---|---|---|
| Yolcu kayıt | ✅ | ✅ | ✅ |
| Ödeme takibi | ✅ | ✅ | ✅ |
| Oda planı | ❌ | ✅ | ✅ |
| Otobüs planı | ❌ | ✅ | ✅ |
| Uçuş listesi | ❌ | ✅ | ✅ |
| Yaka kartı | ❌ | ❌ | ✅ |
| Grup limiti | 1 | 5 | Sınırsız |
| Kullanıcı limiti | 1 | 5 | Sınırsız |
| API erişimi | ❌ | ❌ | ✅ |

## 4. API Taslağı (MVP)
```
POST   /auth/login
POST   /tenants                     (yeni acente kaydı — admin panelinden)
GET    /passengers
POST   /passengers
PUT    /passengers/:id
DELETE /passengers/:id
GET    /groups
POST   /groups
GET    /payments?passenger_id=
POST   /payments
GET    /reports/passengers?group_id=&format=excel|pdf
GET    /reports/payments?format=excel|pdf
```

## 5. Güvenlik Gereksinimleri
- national_id ve passport_no alanları şifrelenmiş saklanır (AES-256 veya eşdeğeri)
- Tüm hassas veri erişimleri loglanır (kim, ne zaman, hangi kayıt)
- Rol bazlı yetkilendirme: sadece admin pasaport/kimlik no görebilir
- Tenant izolasyonu: her sorguda tenant_id zorunlu filtre
- Şifreler bcrypt/argon2 ile hashlenir, asla düz metin saklanmaz

## 6. Dosya/Rapor Üretimi
- Yaka kartı: HTML template → PDF (tekli/toplu)
- Liste çıktıları: Excel (xlsx) ve PDF export, her modül için ayrı endpoint

## 6a. Ortam Değişkenleri (Environment Variables)
Her ortam (production/staging) kendi `.env` dosyasına sahip olmalı, Dokploy panelinden
tanımlanır, repoya asla commit edilmez:
- `DATABASE_URL` (ortama göre farklı Postgres bağlantısı)
- `JWT_SECRET` / auth secret
- `ENCRYPTION_KEY` (national_id, passport_no şifreleme için)
- Object storage erişim anahtarları (eklendiğinde)

## 7. Açık Kararlar (Geliştirme Sırasında Netleştirilecek)
- [ ] Backend framework seçimi
- [ ] Frontend framework seçimi
- [ ] Çoklu dil desteği gerekli mi?
- [ ] Mobil uygulama / responsive web yeterli mi?
- [ ] Ödeme hatırlatma bildirimleri (SMS/WhatsApp/e-posta) hangi fazda?
