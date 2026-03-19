<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('synchronizer_servers', function (Blueprint $table) {
            $table->string('install_dir')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('synchronizer_servers', function (Blueprint $table) {
            $table->dropColumn('install_dir');
        });
    }
};
