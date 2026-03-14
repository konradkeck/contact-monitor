<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make company_id nullable (conversations may arrive before company is resolved)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conversations ALTER COLUMN company_id DROP NOT NULL');
        } else {
            Schema::table('conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('system_type')->nullable()->after('channel_type')
                ->comment('imap, gmail, slack, discord, whmcs, metricscube');
            $table->string('subject')->nullable()->after('system_type');
            $table->boolean('is_archived')->default(false)->after('last_message_at');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->boolean('sync_protected')->default(false)->after('archived_at')
                ->comment('Prevents sync from soft-deleting this record');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['system_type', 'subject', 'is_archived', 'archived_at', 'sync_protected']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conversations ALTER COLUMN company_id SET NOT NULL');
        } else {
            Schema::table('conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
    }
};
