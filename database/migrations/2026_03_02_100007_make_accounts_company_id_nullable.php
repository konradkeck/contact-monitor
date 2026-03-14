<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accounts can arrive from ingest before company is resolved
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounts ALTER COLUMN company_id DROP NOT NULL');
        } else {
            Schema::table('accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounts ALTER COLUMN company_id SET NOT NULL');
        } else {
            Schema::table('accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
    }
};
