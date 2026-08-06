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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('slug')->unique();
            $table->string('address_line')->nullable();
            $table->string('address_landmark')->nullable();
            $table->string('public_location_precision')->default('approximate');
            $table->unsignedSmallInteger('bedrooms')->default(0);
            $table->decimal('bathrooms', 3, 1)->default(1);
            $table->unsignedSmallInteger('parking_spaces')->default(0);
            $table->unsignedInteger('interior_area_m2')->nullable();
            $table->unsignedInteger('lot_area_m2')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->string('furnishing')->default('unfurnished');
            $table->text('description');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'type']);
            $table->index(['team_id', 'created_at']);
        });

        DB::statement('ALTER TABLE properties ADD COLUMN coordinates geography(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX properties_coordinates_gist ON properties USING GIST (coordinates)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
