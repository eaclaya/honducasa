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
            $table->dropIndex(['currency', 'monthly_rent']);
            $table->renameColumn('monthly_rent', 'price_amount');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('listing_type')->default('rent')->after('type');
            $table->index(['listing_type', 'currency', 'price_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['listing_type', 'currency', 'price_amount']);
            $table->dropColumn('listing_type');
            $table->renameColumn('price_amount', 'monthly_rent');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->index(['currency', 'monthly_rent']);
        });
    }
};
