<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('place search reshapes nominatim results into map feature shape', function () {
    Http::fake([
        '*/search*' => Http::response([
            [
                'place_id' => 157674,
                'lat' => '14.0890680',
                'lon' => '-87.1972827',
                'name' => 'Hospital Escuela Universitario',
                'display_name' => 'Hospital Escuela Universitario, Calle La Salud, Tegucigalpa, Honduras',
            ],
        ]),
    ]);

    $this->getJson(route('places.search', ['q' => 'hospital escuela']))
        ->assertOk()
        ->assertJsonPath('features.0.id', '157674')
        ->assertJsonPath('features.0.geometry.coordinates', [-87.1972827, 14.089068])
        ->assertJsonPath('features.0.properties.name', 'Hospital Escuela Universitario')
        ->assertJsonPath('features.0.properties.full_address', 'Hospital Escuela Universitario, Calle La Salud, Tegucigalpa, Honduras');
});

test('place search falls back to the first address segment when nominatim has no name', function () {
    Http::fake([
        '*/search*' => Http::response([
            [
                'place_id' => 162659,
                'lat' => '14.0865905',
                'lon' => '-87.1927221',
                'name' => '',
                'display_name' => 'Bulevar Suyapa, Tegucigalpa, Honduras',
            ],
        ]),
    ]);

    $this->getJson(route('places.search', ['q' => 'bulevar suyapa']))
        ->assertOk()
        ->assertJsonPath('features.0.properties.name', 'Bulevar Suyapa');
});

test('place search returns no results instead of failing when nominatim is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('nominatim unreachable'));

    $this->getJson(route('places.search', ['q' => 'hospital escuela']))
        ->assertOk()
        ->assertJson(['features' => []]);
});

test('place search requires at least two characters', function () {
    $this->getJson(route('places.search', ['q' => 'h']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});
