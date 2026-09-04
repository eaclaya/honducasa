<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedSmallInteger('bedrooms')->nullable()->default(null)->change();
            $table->decimal('bathrooms', 3, 1)->nullable()->default(null)->change();
            $table->unsignedSmallInteger('parking_spaces')->nullable()->default(null)->change();
            $table->string('furnishing')->nullable()->default(null)->change();
            $table->boolean('utilities_included')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('properties')->whereNull('bedrooms')->update(['bedrooms' => 0]);
        DB::table('properties')->whereNull('bathrooms')->update(['bathrooms' => 1]);
        DB::table('properties')->whereNull('parking_spaces')->update(['parking_spaces' => 0]);
        DB::table('properties')->whereNull('furnishing')->update(['furnishing' => 'unfurnished']);
        DB::table('properties')->whereNull('utilities_included')->update(['utilities_included' => false]);

        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedSmallInteger('bedrooms')->nullable(false)->default(0)->change();
            $table->decimal('bathrooms', 3, 1)->nullable(false)->default(1)->change();
            $table->unsignedSmallInteger('parking_spaces')->nullable(false)->default(0)->change();
            $table->string('furnishing')->nullable(false)->default('unfurnished')->change();
            $table->boolean('utilities_included')->nullable(false)->default(false)->change();
        });
    }
};
