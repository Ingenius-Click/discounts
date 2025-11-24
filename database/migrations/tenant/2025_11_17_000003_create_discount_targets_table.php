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
        Schema::create('discount_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('discount_campaigns')->onDelete('cascade');
            $table->nullableMorphs('targetable'); // Polymorphic: Product, Category, Shipment, etc. - null ID means "all of this type"
            $table->string('target_action')->default('apply_to'); // apply_to, requires, excludes
            $table->json('metadata')->nullable(); // Extra params like required_quantity
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_targets');
    }
};
