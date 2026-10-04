<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('marhal:keys {--show : Anahtarları .env\'e yazmadan sadece göster}')]
#[Description('Kimlik/pasaport şifreleme (ENCRYPTION_KEY) ve arama (HASH_KEY) anahtarlarını üretir')]
class GenerateSensitiveDataKeys extends Command
{
    public function handle(): int
    {
        $keys = [
            'ENCRYPTION_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'HASH_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        ];

        if ($this->option('show')) {
            foreach ($keys as $name => $value) {
                $this->line("{$name}={$value}");
            }

            return self::SUCCESS;
        }

        $path = base_path('.env');

        if (! file_exists($path)) {
            $this->error('.env dosyası bulunamadı.');

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);

        foreach ($keys as $name => $value) {
            if (preg_match("/^{$name}=.+$/m", $env)) {
                $this->warn("{$name} zaten tanımlı, değiştirilmedi. (Değiştirmek mevcut şifreli verileri okunamaz yapar.)");

                continue;
            }

            $env = preg_match("/^{$name}=$/m", $env)
                ? preg_replace("/^{$name}=$/m", "{$name}={$value}", $env)
                : Str::finish($env, PHP_EOL)."{$name}={$value}".PHP_EOL;

            $this->info("{$name} oluşturuldu.");
        }

        file_put_contents($path, $env);

        $this->warn('Bu anahtarları güvenli bir yerde yedekleyin; kaybolursa şifreli veriler geri getirilemez.');

        return self::SUCCESS;
    }
}
