<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE ai_chat_messages ADD COLUMN IF NOT EXISTS content_tsv tsvector");
        DB::statement("CREATE INDEX IF NOT EXISTS ai_chat_messages_content_tsv_idx ON ai_chat_messages USING GIN(content_tsv)");
        DB::statement("
            CREATE OR REPLACE FUNCTION ai_chat_messages_tsv_update() RETURNS trigger AS $$
            BEGIN
                NEW.content_tsv := to_tsvector('english', COALESCE(NEW.content, ''));
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
        DB::statement("DROP TRIGGER IF EXISTS ai_chat_messages_tsv_trigger ON ai_chat_messages");
        DB::statement("
            CREATE TRIGGER ai_chat_messages_tsv_trigger
            BEFORE INSERT OR UPDATE OF content ON ai_chat_messages
            FOR EACH ROW EXECUTE FUNCTION ai_chat_messages_tsv_update();
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP TRIGGER IF EXISTS ai_chat_messages_tsv_trigger ON ai_chat_messages");
        DB::statement("DROP FUNCTION IF EXISTS ai_chat_messages_tsv_update()");
        DB::statement("DROP INDEX IF EXISTS ai_chat_messages_content_tsv_idx");
        Schema::table('ai_chat_messages', function ($table) {
            $table->dropColumn('content_tsv');
        });
    }
};
