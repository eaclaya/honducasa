<?php

use App\Enums\ListingType;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('property searches can distinguish rentals from properties for sale', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);

    Property::factory()->for($location)->create([
        'name' => 'Rental Home',
        'listing_type' => ListingType::Rent,
        'price_amount' => 15_000,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'Home For Sale',
        'listing_type' => ListingType::Buy,
        'price_amount' => 3_500_000,
    ]);

    $this->get(route('rentals.index', [
        'location' => 'Tegucigalpa',
        'listing_type' => ListingType::Buy->value,
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('filters.listingType', ListingType::Buy->value)
        ->has('properties.data', 1)
        ->where('properties.data.0.name', 'Home For Sale')
        ->where('properties.data.0.listingType', ListingType::Buy->value)
        ->where('properties.data.0.priceAmount', 3_500_000));
});
