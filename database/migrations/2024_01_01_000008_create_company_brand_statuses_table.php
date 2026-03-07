<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_brand_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_product_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->unsignedSmallInteger('evaluation_score')->nullable();
            $table->text('evaluation_notes')->nullable();
            $table->datetime('last_evaluated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'brand_product_id'], 'company_brand_unique');
            $table->index('company_id');
            $table->index('brand_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_brand_statuses');
    }
};
