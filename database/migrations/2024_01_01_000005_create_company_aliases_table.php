<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('alias_normalized')->comment('lowercase+trim of alias');
            $table->string('type')->nullable()->comment('e.g. trade_name, abbreviation, legacy');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'alias_normalized', 'type']);
            $table->index('alias_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_aliases');
    }
};
