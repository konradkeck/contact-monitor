<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 30); // claude, openai, gemini, grok
            $table->text('api_key');        // encrypted
            $table->jsonb('extra_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_model_configs', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 50)->unique();
            $table->foreignId('credential_id')->constrained('ai_credentials')->cascadeOnDelete();
            $table->string('model_name', 100);
            $table->foreignId('helper_credential_id')->nullable()->constrained('ai_credentials')->nullOnDelete();
            $table->string('helper_model_name', 100)->nullable();
            $table->jsonb('extra_config')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 50);
            $table->foreignId('credential_id')->nullable()->constrained('ai_credentials')->nullOnDelete();
            $table->string('model_name', 100);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_input_usd', 10, 6)->default(0);
            $table->decimal('cost_output_usd', 10, 6)->default(0);
            $table->string('prompt_excerpt', 200)->nullable();
            $table->jsonb('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('ai_chats')->cascadeOnDelete();
            $table->string('role', 20); // user, assistant, tool
            $table->text('content');
            $table->jsonb('tool_calls_json')->nullable();
            $table->jsonb('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('company_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('content'); // markdown
            $table->string('model_name', 100);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('conversation_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary_type', 30); // message, company, person
            $table->text('content');
            $table->string('model_name', 100);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_summaries');
        Schema::dropIfExists('company_analyses');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chats');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_model_configs');
        Schema::dropIfExists('ai_credentials');
    }
};
