<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('website_url', 2048)->nullable()->after('email');
            $table->json('social_profiles')->nullable()->after('custom_fields');
            $table->json('contact_links')->nullable()->after('social_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'social_profiles', 'contact_links']);
        });
    }
};
