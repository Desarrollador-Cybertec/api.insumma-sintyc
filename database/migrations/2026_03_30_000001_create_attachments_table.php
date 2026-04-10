<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('disk')->default('supabase');
            $table->string('bucket')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('original_name');
            $table->string('stored_name')->nullable();
            $table->string('mime_type');
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_original');
            $table->unsignedBigInteger('size_processed')->nullable();
            $table->string('processing_status')->default('pending');
            $table->string('visibility_scope')->default('task');
            $table->string('checksum', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index('processing_status');
            $table->index('visibility_scope');
            $table->index(['task_id', 'processing_status']);
            $table->index(['area_id', 'processing_status']);
            $table->index(['owner_user_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
