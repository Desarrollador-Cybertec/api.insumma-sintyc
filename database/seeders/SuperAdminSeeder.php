<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('slug', 'superadmin')->firstOrFail();

        $superadmins = [
            [
                'name'  => 'Anthor Villamizar',
                'email' => 'anthor.villamizar@cybertec.com.co',
            ],
        ];

        foreach ($superadmins as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make('Admin2026!'),
                    'role_id'           => $role->id,
                    'active'            => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
