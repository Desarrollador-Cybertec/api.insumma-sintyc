<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Administrador', 'slug' => 'superadmin'],
            ['name' => 'Gerente',             'slug' => 'gerente'],
            ['name' => 'Encargado de Área',   'slug' => 'area_manager'],
            ['name' => 'Director',            'slug' => 'director'],
            ['name' => 'Líder',               'slug' => 'leader'],
            ['name' => 'Coordinador',         'slug' => 'coordinator'],
            ['name' => 'Trabajador',          'slug' => 'worker'],
            ['name' => 'Analista',            'slug' => 'analyst'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name'], 'is_active' => true],
            );
        }
    }
}
