<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('mediable'); // belongs to User, Client, etc.
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('collection', 64)->default('default');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
