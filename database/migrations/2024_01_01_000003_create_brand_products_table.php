<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('variant')->nullable();
            $table->string('slug')->unique();
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_products');
    }
};
