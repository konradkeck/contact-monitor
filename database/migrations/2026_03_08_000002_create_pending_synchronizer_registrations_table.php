<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_synchronizer_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('api_token', 64);   // pre-generated token for the new synchronizer
            $table->timestamp('expires_at');
            $table->timestamp('registered_at')->nullable();
            $table->string('registered_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_synchronizer_registrations');
    }
};
