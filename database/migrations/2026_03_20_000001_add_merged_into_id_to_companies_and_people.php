<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('id');
            $table->foreign('merged_into_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('id');
            $table->foreign('merged_into_id')->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn('merged_into_id');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn('merged_into_id');
        });
    }
};
