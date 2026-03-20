<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_note_filters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->jsonb('criteria')->default('{}');
            $table->boolean('as_internal_note')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_note_filters');
    }
};
