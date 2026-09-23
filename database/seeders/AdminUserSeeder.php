<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'System Administrator',
                'email' => 'admin@khadilu.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'permissions' => json_encode(['all' => true]),
            ]
        );
    }
}
