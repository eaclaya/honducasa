<?php

use App\Enums\ListingStatus;
use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Property;

test('active Honduran locations can be searched without accent marks', function () {
    $department = Location::factory()->create([
        'name' => 'El Paraíso',
        'slug' => 'el-paraiso',
        'type' => LocationType::Department,
    ]);
    $city = Location::factory()->for($department, 'parent')->create([
        'name' => 'Danlí',
        'slug' => 'danli',
        'type' => LocationType::City,
    ]);
    Property::factory()->for($city)->create();

    Location::factory()->inactive()->create([
        'name' => 'Danlí Viejo',
        'slug' => 'danli-viejo',
    ]);
    Location::factory()->create([
        'country_code' => 'GT',
        'name' => 'Danlí Guatemala',
        'slug' => 'danli-guatemala',
    ]);

    $this->getJson(route('locations.search', ['q' => 'danli']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Danlí')
        ->assertJsonPath('data.0.type', LocationType::City->value)
        ->assertJsonPath('data.0.context', 'El Paraíso')
        ->assertJsonPath('data.0.listingCount', 1);
});

test('location suggestions only count published properties', function () {
    $city = Location::factory()->create([
        'name' => 'Tegucigalpa',
        'slug' => 'tegucigalpa',
    ]);
    Property::factory()->for($city)->create();
    Property::factory()->for($city)->create(['status' => ListingStatus::Draft]);

    $this->getJson(route('locations.search', ['q' => 'tegu']))
        ->assertOk()
        ->assertJsonPath('data.0.listingCount', 1);
});

test('location suggestions require at least two characters', function () {
    $this->getJson(route('locations.search', ['q' => 't']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});
