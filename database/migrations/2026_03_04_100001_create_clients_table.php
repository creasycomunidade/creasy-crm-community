<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('logo_disk')->nullable()->default('public');
            $table->string('primary_color', 7)->nullable();
            $table->string('custom_domain')->nullable();
            $table->string('tagline')->nullable();
            $table->string('support_email')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 64)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('base_currency', 10)->default('BRL');
            $table->string('currency_symbol', 16)->default('R$');
            $table->string('currency_position', 32)->default('before');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
