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
            $table->decimal('normalized_price_amount', 20, 6)->nullable()->after('currency');
            $table->char('normalized_currency', 3)->nullable()->after('normalized_price_amount');
            $table->decimal('normalization_rate', 20, 10)->nullable()->after('normalized_currency');
            $table->timestamp('price_normalized_at')->nullable()->after('normalization_rate');
            $table->index(
                ['status', 'listing_type', 'normalized_currency', 'normalized_price_amount'],
                'properties_normalized_price_search_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_normalized_price_search_index');
            $table->dropColumn([
                'normalized_price_amount',
                'normalized_currency',
                'normalization_rate',
                'price_normalized_at',
            ]);
        });
    }
};
