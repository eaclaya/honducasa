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
            $table->unsignedInteger('monthly_rent')->default(0)->after('furnishing');
            $table->char('currency', 3)->default('HNL')->after('monthly_rent');
            $table->unsignedInteger('deposit_amount')->nullable()->after('currency');
            $table->boolean('utilities_included')->default(false)->after('deposit_amount');

            $table->index(['currency', 'monthly_rent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['currency', 'monthly_rent']);
            $table->dropColumn(['monthly_rent', 'currency', 'deposit_amount', 'utilities_included']);
        });
    }
};
