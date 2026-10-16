<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('phone_e164', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('first_name', 128)->nullable();
            $table->string('last_name', 128)->nullable();
            $table->string('avatar', 512)->nullable();
            $table->string('country', 4)->nullable();
            $table->string('language', 8)->nullable();
            $table->boolean('opt_in_whatsapp')->default(false);
            $table->boolean('opt_in_sms')->default(false);
            $table->boolean('opt_in_email')->default(true);
            $table->json('custom_fields')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'phone_e164']);
            $table->index('workspace_id');
            $table->index('email');
        });

        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 64);
            $table->string('color', 16)->default('#6366f1');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('contact_tag_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['contact_id', 'tag_id']);
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 128);
            $table->enum('type', ['static', 'dynamic'])->default('static');
            $table->json('rules_json')->nullable();
            $table->unsignedInteger('contact_count')->default(0);
            $table->timestamps();

            $table->index('workspace_id');
        });

        Schema::create('segment_contact', function (Blueprint $table) {
            $table->unsignedBigInteger('segment_id');
            $table->unsignedBigInteger('contact_id');
            $table->primary(['segment_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_contact');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('contact_tag_pivot');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('contacts');
    }
};
