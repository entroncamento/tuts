<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminBackofficeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚜 A criar utilizador Admin de Demonstração...');

        $admin = User::firstOrCreate(
            ['email' => 'admin@ua.pt'],
            [
                'name' => 'Administrador do Sistema',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@ua.pt'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Utilizadores Admin criados com sucesso!');
        $this->command->info('👨‍💻 Admin: admin@ua.pt / password123');
        $this->command->info('👨‍💻 Super Admin: superadmin@ua.pt / password123');
    }
}
