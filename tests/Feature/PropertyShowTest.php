<?php

use App\Data\GeoPoint;
use App\Enums\ListingType;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use App\Enums\TeamRole;
use App\Models\Property;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a public property detail page includes gallery pricing owner and approximate location', function () {
    Storage::fake('public');
    $property = Property::factory()->create([
        'team_id' => null,
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
            ->has('property.publisher.name')
            ->where('property.publisher.agentName', null)
            ->where('property.publisher.isAgency', false)
            ->missing('property.publisher.email')
            ->where('property.messaging.canMessage', false)
            ->has('property.map.latitude')
            ->has('property.map.longitude')
            ->where('property.map.precision', 'approximate'));
});

test('a USD property detail displays the converted base price and preserves the original asking price', function () {
    $property = Property::factory()->create([
        'price_amount' => 1_000,
        'currency' => 'USD',
    ]);

    $this->get(route('properties.show', $property))
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.priceAmount', 24_700)
            ->where('property.currency', 'HNL')
            ->where('property.originalPriceAmount', 1_000)
            ->where('property.originalCurrency', 'USD')
            ->where('property.priceIsConverted', true));
});

test('an agency listing presents the agency and its agent', function () {
    $agent = User::factory()->create(['name' => 'Ana Lopez']);
    $agency = Team::factory()->create(['name' => 'Acme Realty']);
    $agency->members()->attach($agent, ['role' => TeamRole::Owner->value]);
    $property = Property::factory()->create([
        'team_id' => $agency->id,
        'created_by' => $agent->id,
    ]);

    $this->get(route('properties.show', $property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.publisher.name', 'Acme Realty')
            ->where('property.publisher.agentName', 'Ana Lopez')
            ->where('property.publisher.isAgency', true));
});

test('an individual publisher is not presented as an agency', function () {
    $publisher = User::factory()->create(['name' => 'Ana Lopez']);
    $property = Property::factory()->create([
        'team_id' => null,
        'created_by' => $publisher->id,
    ]);

    $this->get(route('properties.show', $property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.publisher.name', 'Ana Lopez')
            ->where('property.publisher.agentName', null)
            ->where('property.publisher.isAgency', false)
            ->missing('property.publisher.teamName'));
});

test('an exact property detail page preserves the stored coordinate precision', function () {
    $property = Property::factory()->at(new GeoPoint(14.076543, -87.192345))->create([
        'public_location_precision' => LocationPrecision::Exact,
    ]);

    $this->get(route('properties.show', $property))
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.map.latitude', 14.076543)
            ->where('property.map.longitude', -87.192345)
            ->where('property.map.precision', 'exact'));
});

test('an approximate property detail page rounds the public map coordinates', function () {
    $property = Property::factory()->at(new GeoPoint(14.076543, -87.192345))->create([
        'public_location_precision' => LocationPrecision::Approximate,
    ]);

    $this->get(route('properties.show', $property))
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.map.latitude', 14.08)
            ->where('property.map.longitude', -87.19)
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
