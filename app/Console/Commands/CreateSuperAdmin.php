<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('marhal:super-admin')]
#[Description('Platform yöneticisi (super_admin) hesabı oluşturur')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $name = text('Ad soyad', required: true);
        $email = text('E-posta', required: true, validate: fn (string $value) => Validator::make(
            ['email' => $value],
            ['email' => ['email', 'unique:users,email']],
        )->errors()->first('email') ?: null);
        $password = password('Şifre (en az 12 karakter)', required: true, validate: fn (string $value) => strlen($value) < 12 ? 'En az 12 karakter olmalı.' : null);

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Platform yöneticisi oluşturuldu: {$email}");

        return self::SUCCESS;
    }
}
