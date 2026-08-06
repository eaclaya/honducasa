<?php

use Inertia\Testing\AssertableInertia as Assert;

test('renders the public homepage', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('renders the public rental search page', function () {
    $response = $this->get(route('rentals.index', [
        'location' => 'Tegucigalpa',
        'radius' => 10,
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('rentals/Index'));
});
