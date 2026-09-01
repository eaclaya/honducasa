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
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['status', 'listing_type', 'type']);
            $table->index(['status', 'currency', 'price_amount']);
            $table->index(['status', 'bedrooms', 'bathrooms', 'parking_spaces']);
            $table->index(['status', 'furnishing', 'utilities_included']);
            $table->index(['status', 'interior_area_m2']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['status', 'listing_type', 'type']);
            $table->dropIndex(['status', 'currency', 'price_amount']);
            $table->dropIndex(['status', 'bedrooms', 'bathrooms', 'parking_spaces']);
            $table->dropIndex(['status', 'furnishing', 'utilities_included']);
            $table->dropIndex(['status', 'interior_area_m2']);
        });
    }
};
