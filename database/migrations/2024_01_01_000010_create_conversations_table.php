<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('primary_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('channel_type')->comment('email, slack, discord, ticket, ...');
            $table->string('system_slug')->default('default')->comment('Multi-instance discriminator');
            $table->string('external_thread_id')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->datetime('started_at')->nullable();
            $table->datetime('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('primary_person_id');
            $table->index(['channel_type', 'system_slug']);
            $table->index('last_message_at');
        });

        // Partial unique index — PostgreSQL (and SQLite) native syntax
        // Only enforces uniqueness when external_thread_id is set
        DB::statement(
            'CREATE UNIQUE INDEX conversations_channel_slug_thread_unique
             ON conversations (channel_type, system_slug, external_thread_id)
             WHERE external_thread_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
