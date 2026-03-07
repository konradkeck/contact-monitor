<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->unique();
            $table->string('source_type')->comment('imap, gmail, slack, discord, whmcs, metricscube');
            $table->string('source_slug')->comment('Connection system_slug');
            $table->unsignedInteger('item_count')->default(0);
            $table->string('status')->default('pending')->comment('pending, processing, done, failed');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_slug']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('ingest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingest_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 64)->unique()
                ->comment('sha256(system_type:system_slug:type:external_id:payload_hash)');
            $table->string('item_type')->comment('conversation, message, identity, account, activity');
            $table->string('action')->default('upsert')->comment('upsert, delete');
            $table->string('system_type');
            $table->string('system_slug');
            $table->string('external_id');
            $table->string('payload_hash', 64)->comment('sha256 of payload JSON');
            $table->jsonb('payload');
            $table->string('status')->default('pending')->comment('pending, done, failed, skipped');
            $table->string('entity_type')->nullable()->comment('App\\Models\\Conversation etc.');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['item_type', 'status']);
            $table->index(['system_type', 'system_slug', 'external_id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_items');
        Schema::dropIfExists('ingest_batches');
    }
};
