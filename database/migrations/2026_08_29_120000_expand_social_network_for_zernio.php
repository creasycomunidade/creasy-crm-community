<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_media_accounts', function (Blueprint $table) {
            $table->string('network', 40)->change();
        });
    }

    public function down(): void
    {
        // The original enum cannot safely be restored while Zernio-only
        // platforms may still be stored in this column.
    }
};
