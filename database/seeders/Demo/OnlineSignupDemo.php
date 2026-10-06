<?php

namespace Database\Seeders\Demo;

use App\Actions\Signup\SignupLinks;
use App\Actions\Signup\SubmitSignupRequest;
use App\Models\NeedType;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;

/**
 * Tasarım yenileme 7b örnek verisi: sıradaki tura ön kayıt linki ve onay bekleyen iki örnek başvuru
 * (uydurma kişiler; gerçek yolcu verisi yok). Link çıktıda yazar.
 */
class OnlineSignupDemo
{
    public function run(Tenant $tenant): string
    {
        $tour = Tour::query()->active()->orderBy('start_date')->first();
        $admin = User::query()->where('tenant_id', $tenant->id)->where('role', 'admin')->first();

        if ($tour === null || $admin === null) {
            return 'Aktif tur ya da yönetici olmadığı için ön kayıt örneği eklenmedi.';
        }

        $link = app(SignupLinks::class)->create($tour, $admin);
        $walking = NeedType::query()->where('name', 'Yürüme güçlüğü')->value('id');

        $samples = [
            [['first_name' => 'Hatice', 'last_name' => 'Örnekoğlu', 'gender' => 'kadin', 'birth_date' => '1958-03-02', 'nationality' => 'TR', 'passport_no' => 'U90000001', 'passport_expiry_date' => '2032-05-01', 'phone' => '0500 000 00 01'], $walking ? [$walking] : [], true],
            [['first_name' => 'Osman', 'last_name' => 'Denemeci', 'gender' => 'erkek', 'birth_date' => '1965-11-20', 'nationality' => 'TR', 'passport_no' => 'U90000002', 'passport_expiry_date' => '2031-01-15', 'phone' => '0500 000 00 02'], [], false],
        ];

        foreach ($samples as [$person, $needs, $fromPassport]) {
            app(SubmitSignupRequest::class)->handle($link, $person, $needs, true, $needs !== [], $fromPassport);
        }

        $link->forceFill(['opened_count' => 5])->save();

        return 'Ön kayıt linki ve 2 örnek başvuru eklendi. Link: '.route('signup.show', $link->token);
    }
}
