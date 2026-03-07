<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('identity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->enum('direction', ['customer', 'internal', 'system'])->default('customer');
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->json('attachments_json')->nullable();
            $table->string('thread_key')->nullable()->index();
            $table->unsignedInteger('thread_count')->default(0);
            $table->string('source_url')->nullable();
            $table->boolean('is_system_message')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
