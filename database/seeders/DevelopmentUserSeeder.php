<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        collect([
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'is_admin' => true],
            ['name' => 'Test User', 'email' => 'test@example.com', 'is_admin' => false],
        ])->each(function (array $user): void {
            User::query()->updateOrCreate([
                'email' => $user['email'],
            ], [
                'name' => $user['name'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_admin' => $user['is_admin'],
                'is_active' => true,
            ]);
        });
    }
}
