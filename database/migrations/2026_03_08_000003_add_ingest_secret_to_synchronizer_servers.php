<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('synchronizer_servers', function (Blueprint $table) {
            $table->string('ingest_secret')->nullable()->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('synchronizer_servers', function (Blueprint $table) {
            $table->dropColumn('ingest_secret');
        });
    }
};
