<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ai_logs', 'mcp_logs');
    }

    public function down(): void
    {
        Schema::rename('mcp_logs', 'ai_logs');
    }
};
