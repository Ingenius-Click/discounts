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
        Schema::create('discount_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id'); // Keep reference but don't cascade delete
            $table->unsignedBigInteger('customer_id')->nullable(); // From userable_id
            $table->morphs('orderable'); // Polymorphic: Order or any other orderable entity
            $table->integer('discount_amount_applied'); // Amount saved in cents
            $table->timestamp('used_at');
            $table->json('metadata')->nullable(); // Extra info like affected products
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_usages');
    }
};
