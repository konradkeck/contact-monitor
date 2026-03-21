<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')
                ->constrained('ai_projects')->nullOnDelete();
            $table->boolean('title_is_manual')->default(false)->after('title');
            $table->boolean('is_archived')->default(false)->after('title_is_manual');
            $table->boolean('is_shared')->default(false)->after('is_archived');
            $table->unsignedBigInteger('source_chat_id')->nullable()->after('is_shared');
            $table->unsignedBigInteger('source_message_id')->nullable()->after('source_chat_id');
            $table->timestamp('last_message_at')->nullable()->after('source_message_id');
        });

        // Self-referential FK for branching
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->foreign('source_chat_id')
                ->references('id')->on('ai_chats')
                ->nullOnDelete();
        });

        // Indexes for common queries
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->index(['user_id', 'is_archived', 'last_message_at']);
            $table->index(['project_id']);
        });

        // FTS on PostgreSQL only
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ai_chats ADD COLUMN IF NOT EXISTS title_tsv tsvector");
            DB::statement("CREATE INDEX IF NOT EXISTS ai_chats_title_tsv_idx ON ai_chats USING GIN(title_tsv)");
            DB::statement("
                CREATE OR REPLACE FUNCTION ai_chats_tsv_update() RETURNS trigger AS $$
                BEGIN
                    NEW.title_tsv := to_tsvector('english', COALESCE(NEW.title, ''));
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");
            DB::statement("DROP TRIGGER IF EXISTS ai_chats_tsv_trigger ON ai_chats");
            DB::statement("
                CREATE TRIGGER ai_chats_tsv_trigger
                BEFORE INSERT OR UPDATE OF title ON ai_chats
                FOR EACH ROW EXECUTE FUNCTION ai_chats_tsv_update();
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP TRIGGER IF EXISTS ai_chats_tsv_trigger ON ai_chats");
            DB::statement("DROP FUNCTION IF EXISTS ai_chats_tsv_update()");
            DB::statement("DROP INDEX IF EXISTS ai_chats_title_tsv_idx");
        }

        Schema::table('ai_chats', function (Blueprint $table) {
            $table->dropForeign(['source_chat_id']);
            $table->dropIndex(['user_id', 'is_archived', 'last_message_at']);
            $table->dropIndex(['project_id']);
            $table->dropColumn([
                'project_id', 'title_is_manual', 'is_archived', 'is_shared',
                'source_chat_id', 'source_message_id', 'last_message_at',
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            Schema::table('ai_chats', function (Blueprint $table) {
                $table->dropColumn('title_tsv');
            });
        }
    }
};
