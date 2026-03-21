<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_project_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->constrained('ai_chats')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('ai_projects')->cascadeOnDelete();
            $table->timestamp('pinned_at')->useCurrent();

            $table->unique(['user_id', 'chat_id']);
            $table->index(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_project_pins');
    }
};
