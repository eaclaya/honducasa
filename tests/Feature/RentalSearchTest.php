<?php

use App\Enums\LocationType;
use App\Enums\PropertyType;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('rentals can be searched by Honduran city', function () {
    $tegucigalpa = Location::factory()->create([
        'name' => 'Tegucigalpa',
        'slug' => 'tegucigalpa',
        'type' => LocationType::City,
    ]);
    $sanPedroSula = Location::factory()->create([
        'name' => 'San Pedro Sula',
        'slug' => 'san-pedro-sula',
        'type' => LocationType::City,
    ]);

    Property::factory()->for($tegucigalpa)->create(['name' => 'Capital Apartment']);
    Property::factory()->for($sanPedroSula)->create(['name' => 'Valley House']);

    $this->get(route('rentals.index', ['location' => 'tegucigalpa']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rentals/Index')
            ->where('filters.location', 'tegucigalpa')
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Capital Apartment')
            ->where('properties.data.0.location', 'Tegucigalpa'));
});

test('rentals can be filtered by property type', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);

    Property::factory()->for($location)->create(['type' => PropertyType::Apartment]);
    Property::factory()->for($location)->create(['type' => PropertyType::House]);

    $this->get(route('rentals.index', [
        'location' => 'Tegucigalpa',
        'property_type' => PropertyType::House->value,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('properties.data', 1)
        ->where('properties.data.0.type', PropertyType::House->value));
});
