#!/bin/sh
# Sadece staging: yeni özelliklerin örnek verisini demo acenteye yükler (php artisan marhal:demo-data).
# Her paket bir kez yüklenir; hata olursa uygulamanın açılmasını engellemez.
if [ "$APP_ENV" = "staging" ]; then
    php /var/www/html/artisan marhal:demo-data --no-interaction || echo "marhal:demo-data başarısız (uygulama yine de başlıyor)"
fi
