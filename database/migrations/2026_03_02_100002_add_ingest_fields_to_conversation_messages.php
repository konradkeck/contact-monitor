<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('conversation_id')
                ->comment('External message ID (e.g. Slack ts, Discord message_id, IMAP message_id header)');
            $table->boolean('is_archived')->default(false)->after('meta_json');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->boolean('sync_protected')->default(false)->after('archived_at');

            // Unique per conversation: (conversation_id, external_id) when external_id set
        });

        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX conv_messages_conv_ext_unique
             ON conversation_messages (conversation_id, external_id)
             WHERE external_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS conv_messages_conv_ext_unique');
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'is_archived', 'archived_at', 'sync_protected']);
        });
    }
};
