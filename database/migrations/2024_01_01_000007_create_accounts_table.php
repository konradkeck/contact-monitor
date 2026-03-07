<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('system_type')->comment('whmcs, metricscube, ...');
            $table->string('system_slug')->default('default')->comment('Multi-instance discriminator');
            $table->string('external_id');
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['system_type', 'system_slug', 'external_id'], 'accounts_system_type_slug_external_unique');
            $table->index('company_id');
            $table->index(['system_type', 'system_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
