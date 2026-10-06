<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hassas veri anahtarları
    |--------------------------------------------------------------------------
    |
    | Kimlik / pasaport numaralarını şifrelemek (ENCRYPTION_KEY) ve aranabilir
    | hash'lerini üretmek (HASH_KEY) için kullanılır. APP_KEY'den ayrıdır.
    | Kaybolurlarsa şifreli veriler geri getirilemez — güvenli yerde yedekleyin.
    |
    */

    'encryption_key' => env('ENCRYPTION_KEY'),

    'hash_key' => env('HASH_KEY'),

    /*
    | Yolcu fotoğrafı, logo gibi dosyaların diski. Şimdilik sunucu diski
    | (storage/app/private); ileride S3 uyumlu depolama için "s3".
    */

    'media_disk' => env('MEDIA_DISK', 'local'),

    /*
    | Desteklenen para birimleri (ISO 4217).
    */

    'currencies' => ['TRY', 'USD', 'EUR', 'SAR'],

    /*
    | Abonelik ödemesi için havale / EFT bilgileri ("Paketim"de acenteye gösterilir).
    | Gerçek değerler sunucu ortam değişkenlerinde; boşsa "bizimle iletişime geçin" yazılır.
    */

    'billing' => [
        'bank' => env('BILLING_BANK'),
        'iban' => env('BILLING_IBAN'),
        'holder' => env('BILLING_ACCOUNT_HOLDER'),
    ],

];
