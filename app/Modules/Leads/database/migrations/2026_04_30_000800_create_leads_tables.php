<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 256)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 256)->nullable();
            $table->string('website', 512)->nullable();
            $table->string('address', 512)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('country', 64)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('category', 128)->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->string('google_place_id', 128)->nullable()->unique();
            $table->enum('whatsapp_status', ['unknown', 'valid', 'invalid'])->default('unknown');
            $table->boolean('pushed_to_contacts')->default(false);
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'phone']);
        });

        Schema::create('lead_scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('keyword', 256);
            $table->string('location', 256);
            $table->unsignedInteger('radius_meters')->default(5000);
            $table->enum('status', ['pending', 'running', 'done', 'failed'])->default('pending');
            $table->unsignedInteger('leads_found')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'status'], 'lead_scrape_jobs_ws_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_scrape_jobs');
        Schema::dropIfExists('leads');
    }
};
