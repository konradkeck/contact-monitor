<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('identity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable()->comment('e.g. sender, recipient, cc');
            $table->string('display_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['conversation_id', 'identity_id'], 'conv_participant_unique');
            $table->index('conversation_id');
            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
