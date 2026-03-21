<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('type', 20); // free_email, disposable
            $table->string('source', 50)->default('manual');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['domain', 'type']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_classifications');
    }
};
