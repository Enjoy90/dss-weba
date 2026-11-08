<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Admin Sistem', 'admin@spk.test', UserRole::Admin],
            ['Budi Santoso', 'manajer@spk.test', UserRole::Manager],
            ['Siti Rahmawati', 'supervisor@spk.test', UserRole::Supervisor],
            ['Hendra Wijaya', 'pimpinan@spk.test', UserRole::Leader],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'role' => $role, 'is_active' => true],
            );
        }
    }
}
