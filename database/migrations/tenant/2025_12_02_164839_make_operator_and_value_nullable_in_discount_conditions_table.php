<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discount_conditions', function (Blueprint $table) {
            $table->string('operator')->nullable()->default(null)->change();
            $table->json('value')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_conditions', function (Blueprint $table) {
            $table->string('operator')->default('>=')->change();
            $table->json('value')->nullable(false)->change();
        });
    }
};
