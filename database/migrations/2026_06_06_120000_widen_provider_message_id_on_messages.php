<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram message ids (mid) are long base64-style strings (~180 chars),
     * well beyond the original varchar(128). Widen the column so inbound/outbound
     * Instagram messages can store their provider id. varchar(255) keeps the
     * existing index within InnoDB's key-length limit.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('provider_message_id', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('provider_message_id', 128)->nullable()->change();
        });
    }
};
