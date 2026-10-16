<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_chatbots');
        Schema::dropIfExists('ai_kb_chunks');
        Schema::dropIfExists('ai_kb_documents');
        Schema::dropIfExists('ai_knowledge_bases');
        Schema::dropIfExists('ai_provider_configs');

        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->enum('provider', ['openai', 'anthropic', 'gemini']);
            $table->text('credentials')->nullable();
            $table->string('default_model_chat', 64)->nullable();
            $table->string('default_model_embed', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });

        Schema::create('ai_knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 128);
            $table->string('embedding_model', 64)->default('text-embedding-3-small');
            $table->unsignedInteger('dimensions')->default(1536);
            $table->enum('status', ['active', 'indexing', 'error'])->default('active');
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'status'], 'ai_kbs_ws_status_idx');
        });

        Schema::create('ai_kb_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('kb_id');
            $table->enum('source_type', ['file', 'url', 'text', 'sitemap', 'faq']);
            $table->string('source_ref', 512)->nullable();
            $table->string('title', 256)->nullable();
            $table->enum('status', ['pending', 'indexing', 'indexed', 'error'])->default('pending');
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamps();

            $table->foreign('kb_id')->references('id')->on('ai_knowledge_bases')->cascadeOnDelete();
            $table->index(['kb_id', 'status'], 'ai_kb_docs_kb_status_idx');
        });

        Schema::create('ai_kb_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kb_id')->nullable()->index();
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('ord')->default(0);
            $table->text('content');
            $table->unsignedInteger('tokens')->default(0);
            $table->longText('embedding')->nullable();
            $table->timestamps();

            $table->index('document_id');
        });

        Schema::create('ai_chatbots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 128);
            $table->unsignedBigInteger('ai_kb_id')->nullable();
            $table->text('system_prompt')->nullable();
            $table->string('tone', 64)->default('professional');
            $table->unsignedTinyInteger('max_context_chunks')->default(5);
            $table->string('fallback_reply', 512)->nullable();
            $table->json('channels')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index('workspace_id');
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chatbot_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('model', 64)->nullable();
            $table->enum('status', ['ok', 'error', 'guardrail_trip'])->default('ok');
            $table->timestamps();

            $table->index('chatbot_id');
            $table->index('conversation_id');
            $table->index(['chatbot_id', 'created_at'], 'ai_runs_chatbot_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_chatbots');
        Schema::dropIfExists('ai_kb_chunks');
        Schema::dropIfExists('ai_kb_documents');
        Schema::dropIfExists('ai_knowledge_bases');
        Schema::dropIfExists('ai_provider_configs');
    }
};
