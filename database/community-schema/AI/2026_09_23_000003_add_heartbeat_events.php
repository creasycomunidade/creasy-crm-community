<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_heartbeats', function (Blueprint $t) {
            $t->json('wake_triggers')->nullable();
        });
        Schema::table('ai_heartbeat_runs', fn (Blueprint $t) => $t->json('trigger_context')->nullable());
        Schema::create('ai_heartbeat_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('heartbeat_id')->constrained('ai_heartbeats')->cascadeOnDelete();
            $t->string('kind', 40);
            $t->uuid('version');
            $t->json('payload');
            $t->timestamps();
            $t->unique(['heartbeat_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_heartbeat_events');
        Schema::table('ai_heartbeat_runs', fn (Blueprint $t) => $t->dropColumn('trigger_context'));
        Schema::table('ai_heartbeats', fn (Blueprint $t) => $t->dropColumn('wake_triggers'));
    }
};
