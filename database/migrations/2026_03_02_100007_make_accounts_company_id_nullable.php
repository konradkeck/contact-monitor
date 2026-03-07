<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Accounts can arrive from ingest before company is resolved
        DB::statement('ALTER TABLE accounts ALTER COLUMN company_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE accounts ALTER COLUMN company_id SET NOT NULL');
    }
};
