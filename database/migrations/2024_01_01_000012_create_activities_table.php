<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->comment('ticket, renewal, payment, cancellation, note, conversation, ...');
            $table->string('reference_type')->nullable()->comment('Morph type');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('Morph id');
            $table->datetime('occurred_at');
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'occurred_at'], 'activities_company_occurred_idx');
            $table->index('type', 'activities_type_idx');
            $table->index(['reference_type', 'reference_id'], 'activities_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
