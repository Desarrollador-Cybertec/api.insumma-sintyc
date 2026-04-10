<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->boolean('requires_progress_report')->default(false);
            $table->boolean('notify_on_due')->default(false);
            $table->boolean('notify_on_overdue')->default(false);
            $table->boolean('notify_on_completion')->default(false);
            $table->unsignedTinyInteger('progress_percent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_id');
            $table->dropColumn([
                'requires_progress_report',
                'notify_on_due',
                'notify_on_overdue',
                'notify_on_completion',
                'progress_percent',
            ]);
        });
    }
};
