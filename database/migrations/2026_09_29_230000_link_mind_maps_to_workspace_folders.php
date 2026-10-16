<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_mind_maps', function (Blueprint $table) {
            $table->foreignId('workspace_folder_id')->nullable()->constrained('workspace_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_mind_maps', fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_folder_id'));
    }
};
