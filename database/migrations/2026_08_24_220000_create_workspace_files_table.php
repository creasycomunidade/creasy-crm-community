<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('extension', 24)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('storage_path', 600)->nullable();
            $table->longText('content')->nullable();
            $table->string('source', 24)->default('user');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_files');
    }
};
