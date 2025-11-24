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
        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique(); // For coupon codes
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type'); // percentage, fixed_amount, free_shipping, bogo
            $table->integer('discount_value')->default(0); // Percentage or amount in cents
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(50); // Higher runs first
            $table->boolean('is_stackable')->default(false);
            $table->integer('max_uses_total')->nullable(); // Campaign-wide limit
            $table->integer('max_uses_per_customer')->nullable();
            $table->integer('current_uses')->default(0); // Counter
            $table->json('metadata')->nullable(); // Extra config (e.g., BOGO rules)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_campaigns');
    }
};
