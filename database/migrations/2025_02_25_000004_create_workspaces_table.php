<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // workspaces.client_id is a plain column (no FK) — clients are created in a later migration.
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('name');
            $table->string('default_locale', 10)->default('pt');
            $table->string('currency_code', 10)->nullable()->default('BRL');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
