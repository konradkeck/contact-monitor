<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change batch_uuid from PostgreSQL native uuid type to varchar(64)
        // so any string key (UUID or custom) is accepted.
        // SQLite stores everything as text natively — no change needed there.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ingest_batches ALTER COLUMN batch_uuid TYPE varchar(64) USING batch_uuid::varchar');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ingest_batches ALTER COLUMN batch_uuid TYPE uuid USING batch_uuid::uuid');
        }
    }
};
