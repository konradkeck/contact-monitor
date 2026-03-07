<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('system_slug')->default('default')->comment('Multi-instance discriminator');
            $table->string('type')->comment('email, slack_id, discord_id, ...');
            $table->string('value')->comment('Raw value as received');
            $table->string('value_normalized')->comment('lowercase+trim of value');
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'system_slug', 'value_normalized'], 'identities_type_slug_value_unique');
            $table->index('person_id');
            $table->index('value_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};
