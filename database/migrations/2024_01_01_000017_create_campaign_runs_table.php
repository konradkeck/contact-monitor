<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued')->comment('queued, running, completed, failed');
            $table->json('parameters_json')->nullable();
            $table->text('result_summary')->nullable();
            $table->string('file_path')->nullable();
            $table->datetime('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('campaign_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_runs');
    }
};
