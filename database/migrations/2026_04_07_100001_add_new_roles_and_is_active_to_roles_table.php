<?php

use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_active column to roles table for configurable roles
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('slug');
        });

        // Insert new roles
        $now = now();
        $newRoles = [
            ['name' => 'Gerente', 'slug' => RoleEnum::GERENTE->value, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Director', 'slug' => RoleEnum::DIRECTOR->value, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Líder', 'slug' => RoleEnum::LEADER->value, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Coordinador', 'slug' => RoleEnum::COORDINATOR->value, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Analista', 'slug' => RoleEnum::ANALYST->value, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($newRoles as $role) {
            DB::table('roles')->insertOrIgnore($role);
        }
    }

    public function down(): void
    {
        // Remove the new roles
        DB::table('roles')->whereIn('slug', [
            RoleEnum::GERENTE->value,
            RoleEnum::DIRECTOR->value,
            RoleEnum::LEADER->value,
            RoleEnum::COORDINATOR->value,
            RoleEnum::ANALYST->value,
        ])->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
