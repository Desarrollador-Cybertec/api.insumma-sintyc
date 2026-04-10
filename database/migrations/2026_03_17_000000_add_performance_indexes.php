<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('area_id');
            $table->index('current_responsible_user_id');
            $table->index('created_by');
            $table->index('assigned_to_user_id');
            $table->index('completed_at');
            $table->index(['area_id', 'status']);
            $table->index(['current_responsible_user_id', 'status']);
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->index('manager_user_id');
            $table->index('active');
        });

        Schema::table('area_members', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['area_id']);
            $table->dropIndex(['current_responsible_user_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['assigned_to_user_id']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['area_id', 'status']);
            $table->dropIndex(['current_responsible_user_id', 'status']);
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropIndex(['manager_user_id']);
            $table->dropIndex(['active']);
        });

        Schema::table('area_members', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
