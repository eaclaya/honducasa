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
        'listing_type' => 'rent',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('rentals/Index'));
});

test('generates secure assets behind the public tunnel proxy', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Host' => 'honducasa.eu-1.sharedwithexpose.com',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-Proto' => 'https',
    ])->get('/');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('https://honducasa.eu-1.sharedwithexpose.com/build/')
        ->not->toContain('http://honducasa.eu-1.sharedwithexpose.com/build/');
});
