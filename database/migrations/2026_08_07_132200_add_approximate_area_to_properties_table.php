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
            $table->string('approximate_shape')->nullable()->after('public_location_precision');
            $table->unsignedInteger('approximate_radius_meters')->nullable()->after('approximate_shape');
            $table->json('approximate_polygon')->nullable()->after('approximate_radius_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'approximate_shape',
                'approximate_radius_meters',
                'approximate_polygon',
            ]);
        });
    }
};
