<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);      // whatsapp, instagram, messenger, stripe, paddle, paypal, twilio, nexmo, messagebird
            $table->string('event_id', 128);     // provider-specific unique event ID
            $table->dateTime('received_at');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index('received_at');        // for pruning
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_events');
    }
};
