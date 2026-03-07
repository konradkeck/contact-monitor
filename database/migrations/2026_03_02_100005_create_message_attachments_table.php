<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('filename');
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('source_url')->nullable();
            $table->string('storage_path')->nullable()->comment('Local storage path if downloaded');
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();

            $table->index('conversation_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
