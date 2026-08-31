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
        Schema::create('listing_photo_enhancements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
            $table->string('draft_key')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['property_id']);
            $table->index(['user_id', 'draft_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_photo_enhancements');
    }
};
