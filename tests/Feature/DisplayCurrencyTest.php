<?php

use App\Models\Location;
use App\Models\Property;
use Inertia\Testing\AssertableInertia as Assert;

test('the display currency defaults to the base currency', function () {
    $this->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currency.display', 'HNL')
            ->where('currency.base', 'HNL')
            ->where('currency.supported', ['HNL', 'USD']));
});

test('choosing a display currency persists it across requests', function () {
    $this->post(route('currency.update', 'USD'))->assertRedirect();

    $this->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currency.display', 'USD')
            ->where('filters.currency', 'USD'));
});

test('an unsupported display currency is rejected', function () {
    $this->post(route('currency.update', 'GBP'))->assertNotFound();

    $this->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page->where('currency.display', 'HNL'));
});

test('the stored display currency converts listed prices', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->create([
        'name' => 'Lempira listing',
        'currency' => 'HNL',
        'price_amount' => 24_700,
    ]);

    $this->withSession(['display_currency' => 'USD'])
        ->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.priceAmount', 1_000)
            ->where('properties.data.0.currency', 'USD')
            ->where('properties.data.0.originalPriceAmount', 24_700)
            ->where('properties.data.0.originalCurrency', 'HNL')
            ->where('properties.data.0.priceIsConverted', true));
});

test('the property page honours the stored display currency', function () {
    $property = Property::factory()->create([
        'currency' => 'HNL',
        'price_amount' => 24_700,
    ]);

    $this->withSession(['display_currency' => 'USD'])
        ->get(route('properties.show', $property))
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.priceAmount', 1_000)
            ->where('property.currency', 'USD'));
});

test('an explicit currency parameter overrides the stored preference so links stay faithful', function () {
    $this->withSession(['display_currency' => 'USD'])
        ->get(route('rentals.index', ['currency' => 'HNL']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currency.display', 'USD')
            ->where('filters.currency', 'HNL'));
});

test('switching currency drops a stale currency override from the return url', function () {
    $this->from(route('rentals.index', ['currency' => 'HNL', 'location' => 'Tegucigalpa']))
        ->post(route('currency.update', 'USD'))
        ->assertRedirect(route('rentals.index', ['location' => 'Tegucigalpa']));
});

test('price bounds are interpreted in the stored display currency', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->create([
        'name' => 'Under 200 USD',
        'currency' => 'HNL',
        'price_amount' => 2_470,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'Over 200 USD',
        'currency' => 'HNL',
        'price_amount' => 24_700,
    ]);

    $this->withSession(['display_currency' => 'USD'])
        ->get(route('rentals.index', ['max_price' => 200]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Under 200 USD'));
});
