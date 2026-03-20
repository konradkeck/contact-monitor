<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_note_filter_id')->nullable()->constrained('smart_note_filters')->nullOnDelete();
            $table->string('source_type', 50);
            $table->string('source_external_id', 500)->nullable();
            $table->text('content');
            $table->string('sender_name', 255)->nullable();
            $table->string('sender_value', 255)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->boolean('as_internal_note')->default(false);
            $table->string('status', 20)->default('unrecognized');
            $table->jsonb('segments_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_notes');
    }
};
