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
        Schema::create('discount_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('discount_campaigns')->onDelete('cascade');
            $table->string('condition_type'); // min_cart_value, min_quantity, customer_segment, has_product, etc.
            $table->string('operator')->default('>='); // >=, ==, in, not_in, etc.
            $table->json('value'); // Flexible storage for condition parameters
            $table->string('logic_operator')->nullable(); // AND/OR for combining with next condition
            $table->integer('priority')->default(1); // Order of evaluation
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_conditions');
    }
};
