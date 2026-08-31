<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sellable catalog. One row here maps to one Product+Price on
     * whichever payment provider we end up using — `provider_price_id` is a
     * reference to that external object, never a value we compute locally.
     *
     * Price and provider fields are reference-only: changing a price means
     * creating a new plan row (and a new provider Price) rather than editing
     * this one, since prices are immutable once real subscribers exist.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('ladder')->index();
            $table->string('name');
            $table->unsignedInteger('active_listings_limit')->nullable();
            $table->unsignedInteger('seats_limit')->nullable();
            $table->unsignedInteger('featured_listing_slots')->default(0);
            $table->string('analytics_tier');
            $table->string('support_tier');
            $table->unsignedInteger('price_amount');
            $table->string('currency', 3);
            $table->string('provider');
            $table->string('provider_price_id')->nullable();
            $table->boolean('is_entry_tier')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
