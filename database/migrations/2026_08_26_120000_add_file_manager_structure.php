<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_folders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('workspace_folders')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'parent_id', 'name']);
        });

        Schema::table('workspace_files', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('created_by')->constrained('workspace_folders')->nullOnDelete();
            $table->index(['workspace_id', 'folder_id', 'name']);
        });

        Schema::create('workspace_file_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_file_id')->constrained('workspace_files')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'expires_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_file_shares');
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
        Schema::dropIfExists('workspace_folders');
    }
};
