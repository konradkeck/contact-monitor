<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type')->comment('App\\Models\\Company, App\\Models\\Person, App\\Models\\Conversation');
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['linkable_type', 'linkable_id'], 'note_links_linkable_idx');
            $table->index('note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};
