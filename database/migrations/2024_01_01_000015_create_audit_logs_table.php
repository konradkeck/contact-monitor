<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No soft deletes — audit logs are immutable
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable()->comment('No FK — intentionally decoupled');
            $table->string('action')->comment('created, updated, deleted, imported, ...');
            $table->string('entity_type')->comment('e.g. App\\Models\\Company');
            $table->unsignedBigInteger('entity_id');
            $table->text('message');
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_idx');
            $table->index('actor_user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
