<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Roles (idempotente via updateOrCreate)
        $this->call(RoleSeeder::class);

        $superadminRole = Role::where('slug', RoleEnum::SUPERADMIN->value)->first();
        $managerRole    = Role::where('slug', RoleEnum::AREA_MANAGER->value)->first();
        $workerRole     = Role::where('slug', RoleEnum::WORKER->value)->first();

        // Create superadmin
        $admin = User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@sintyc.test',
            'password' => Hash::make('Password1'),
            'role_id'  => $superadminRole->id,
        ]);

        // Create area manager
        $manager = User::factory()->create([
            'name'     => 'Manager',
            'email'    => 'manager@sintyc.test',
            'password' => Hash::make('Password1'),
            'role_id'  => $managerRole->id,
        ]);

        // Create workers
        $worker1 = User::factory()->create([
            'name'     => 'Worker 1',
            'email'    => 'worker1@sintyc.test',
            'password' => Hash::make('Password1'),
            'role_id'  => $workerRole->id,
        ]);

        $worker2 = User::factory()->create([
            'name'     => 'Worker 2',
            'email'    => 'worker2@sintyc.test',
            'password' => Hash::make('Password1'),
            'role_id'  => $workerRole->id,
        ]);

        // Create area
        $area = Area::create([
            'name'            => 'Area de Desarrollo',
            'description'     => 'Equipo de desarrollo de software',
            'manager_user_id' => $manager->id,
        ]);

        // Assign workers to area
        AreaMember::create([
            'area_id'     => $area->id,
            'user_id'     => $worker1->id,
            'assigned_by' => $admin->id,
            'joined_at'   => now(),
            'is_active'   => true,
        ]);

        AreaMember::create([
            'area_id'     => $area->id,
            'user_id'     => $worker2->id,
            'assigned_by' => $admin->id,
            'joined_at'   => now(),
            'is_active'   => true,
        ]);

        // Create sample meeting
        Meeting::create([
            'title'          => 'Reunion de kickoff',
            'meeting_date'   => now(),
            'area_id'        => $area->id,
            'classification' => 'operational',
            'notes'          => 'Primera reunion del equipo',
            'created_by'     => $admin->id,
        ]);

        // Seed system configuration and message templates
        $this->call([
            SystemSettingSeeder::class,
            MessageTemplateSeeder::class,
        ]);
    }
}
