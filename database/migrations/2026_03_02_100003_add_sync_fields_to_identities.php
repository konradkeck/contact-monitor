<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identities', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('meta_json');
            $table->boolean('sync_protected')->default(false)->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('identities', function (Blueprint $table) {
            $table->dropColumn(['is_archived', 'sync_protected']);
        });
    }
};
