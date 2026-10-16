<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username');
            $table->text('password'); // stored encrypted
            $table->string('encryption', 16)->default('tls'); // tls, ssl, null
            $table->string('from_email');
            $table->string('from_name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_configurations');
    }
};
