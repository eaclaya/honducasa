<?php

use App\Enums\ListingType;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a public property detail page includes gallery pricing owner and approximate location', function () {
    Storage::fake('public');
    $property = Property::factory()->create([
        'name' => 'Casa Mirador',
        'listing_type' => ListingType::Buy,
        'price_amount' => 4_200_000,
        'currency' => 'HNL',
    ]);

    collect(range(1, 3))->each(fn () => $property
        ->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection('photos'));

    $this->get(route('properties.show', $property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/Show')
            ->where('property.name', 'Casa Mirador')
            ->where('property.listingType', 'buy')
            ->where('property.priceAmount', 4_200_000)
            ->has('property.images', 3)
            ->has('property.publisher.teamName')
            ->has('property.publisher.agentName')
            ->missing('property.publisher.email')
            ->where('property.messaging.canMessage', false)
            ->has('property.map.latitude')
            ->has('property.map.longitude')
            ->where('property.map.precision', 'approximate'));
});

test('property detail page includes the nearest matching related properties', function () {
    $property = Property::factory()->create([
        'type' => PropertyType::House,
        'price_amount' => 20_000,
    ]);
    $closest = Property::factory()->for($property->location)->create([
        'name' => 'Closest match',
        'type' => PropertyType::House,
        'price_amount' => 21_000,
    ]);
    Property::factory()->for($property->location)->create([
        'name' => 'Farther match',
        'type' => PropertyType::House,
        'price_amount' => 30_000,
    ]);
    Property::factory()->for($property->location)->create([
        'name' => 'Wrong type',
        'type' => PropertyType::Apartment,
        'price_amount' => 20_000,
    ]);

    $this->get(route('properties.show', $property))
        ->assertInertia(fn (Assert $page) => $page
            ->has('related', 2)
            ->where('related.0.slug', $closest->slug)
            ->where('related.0.bedrooms', $closest->bedrooms)
            ->where('related.0.interiorAreaM2', $closest->interior_area_m2));
});

test('property detail routes bind by slug', function () {
    $property = Property::factory()->create();

    $this->get('/properties/'.$property->id)->assertNotFound();
    $this->get('/properties/'.$property->slug)->assertOk();
});

test('a property page 404s when its team is suspended', function () {
    $suspendedTeam = Team::factory()->create(['suspended_at' => now()]);
    $property = Property::factory()->create(['team_id' => $suspendedTeam->id]);

    $this->get(route('properties.show', $property))->assertNotFound();
});

test('a suspended team\'s properties are excluded from the related listings sidebar', function () {
    $property = Property::factory()->create();
    $suspendedTeam = Team::factory()->create(['suspended_at' => now()]);
    Property::factory()->create([
        'team_id' => $suspendedTeam->id,
        'location_id' => $property->location_id,
        'listing_type' => $property->listing_type,
        'type' => $property->type,
    ]);

    $this->get(route('properties.show', $property))
        ->assertInertia(fn (Assert $page) => $page->has('related', 0));
});
