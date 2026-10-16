<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64)->default('app');
            $table->string('key')->index();
            $table->string('locale_code', 10)->index();
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key', 'locale_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
