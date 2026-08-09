<?php

use App\Enums\ListingStatus;
use App\Enums\LocationPrecision;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use App\Support\HondurasCityCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests cannot manage listings', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->get(route('listings.index', $user->currentTeam))
        ->assertRedirect(route('login'));
});

test('publishing from the solo wizard creates a personal team and the listing together', function () {
    $user = User::factory()->create(['name' => 'Ana Lopez']);
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->post(
        route('listings.start.store'),
        listingPayload($location),
    );

    $response->assertSessionHasNoErrors();

    $team = $user->fresh()->currentTeam;
    $property = Property::query()->sole();

    expect($team)->not->toBeNull()
        ->and($team->name)->toBe("Ana Lopez's Team")
        ->and($team->is_personal)->toBeTrue()
        ->and($property->team_id)->toBe($team->id);

    $response->assertRedirect(route('listings.index', $team));
});

test('an invalid submission from the solo wizard creates neither a team nor a listing', function () {
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->post(
        route('listings.start.store'),
        listingPayload($location, ['name' => '']),
    );

    $response->assertSessionHasErrors('name');

    expect($user->fresh()->teams()->count())->toBe(0)
        ->and(Property::query()->count())->toBe(0);
});

test('an existing landlord submitting the solo wizard reuses their current team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->post(
        route('listings.start.store'),
        listingPayload($location),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('listings.index', $team));

    expect($user->fresh()->teams()->count())->toBe(1)
        ->and(Property::query()->sole()->team_id)->toBe($team->id);
});

test('a team member can create and publish a listing with an image', function () {
    Storage::fake('public');
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $mediaId = (int) $this->actingAs($user)
        ->post(route('listings.uploads.store'), ['file' => UploadedFile::fake()->image('home.jpg', 1200, 800)])
        ->assertCreated()
        ->getContent();

    $response = $this->actingAs($user)->post(
        route('listings.store', $user->currentTeam),
        listingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => [$mediaId],
        ]),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $property = Property::query()->sole();

    $response->assertRedirect(route('listings.index', $user->currentTeam));
    expect($property->team_id)->toBe($user->current_team_id)
        ->and($property->status)->toBe(ListingStatus::Published)
        ->and($property->published_at)->not->toBeNull()
        ->and($property->getMedia('photos'))->toHaveCount(1);

    Storage::disk('public')->assertExists($property->getFirstMedia('photos')->getPathRelativeToRoot());
});

test('a listing cannot reference another users pending photo', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $intruder = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $mediaId = (int) $this->actingAs($intruder)
        ->post(route('listings.uploads.store'), ['file' => UploadedFile::fake()->image('secret.jpg')])
        ->assertCreated()
        ->getContent();

    $this->actingAs($owner)->post(
        route('listings.store', $owner->currentTeam),
        listingPayload($location, ['images' => [$mediaId]]),
    )->assertSessionHasErrors('images');
});

test('a user can delete their own pending upload but not someone elses', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $intruder = User::factory()->withPersonalTeam()->create();

    $mediaId = (int) $this->actingAs($owner)
        ->post(route('listings.uploads.store'), ['file' => UploadedFile::fake()->image('mine.jpg')])
        ->getContent();

    $this->actingAs($intruder)->delete(route('listings.uploads.destroy', $mediaId))->assertForbidden();
    $this->actingAs($owner)->delete(route('listings.uploads.destroy', $mediaId))->assertNoContent();
});

test('a draft listing is hidden from public pages and search', function () {
    $property = Property::factory()->create([
        'status' => ListingStatus::Draft,
        'published_at' => null,
    ]);

    $this->get(route('properties.show', $property))->assertNotFound();
    $this->get(route('rentals.index', ['location' => $property->location->name]))
        ->assertInertia(fn ($page) => $page->where('properties.data', []));
});

test('validation errors preserve the submitted listing fields', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();
    $payload = listingPayload($location, [
        'name' => 'Mi casa que no debe perderse',
        'bathrooms' => 99,
        'price_amount' => 18_500,
        'bedrooms' => 4,
        'utilities_included' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('listings.create', $user->currentTeam))
        ->post(route('listings.store', $user->currentTeam), $payload);

    $response->assertRedirect(route('listings.create', $user->currentTeam))
        ->assertSessionHasErrors('bathrooms');

    $this->get(route('listings.create', $user->currentTeam))
        ->assertInertia(fn ($page) => $page
            ->where('oldInput.name', $payload['name'])
            ->where('oldInput.price_amount', $payload['price_amount'])
            ->where('oldInput.bedrooms', $payload['bedrooms'])
            ->where('oldInput.utilities_included', $payload['utilities_included']));
});

test('a user cannot manage a listing owned by another team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $property = Property::factory()->create();

    $this->actingAs($user)
        ->get(route('listings.edit', [$user->currentTeam, $property]))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('listings.destroy', [$user->currentTeam, $property]))
        ->assertForbidden();
});

test('the city is derived from the map pin instead of being submitted', function () {
    $user = User::factory()->withPersonalTeam()->create();
    Location::factory()->hondurasCity()->create();
    $sanPedroSula = Location::factory()->hondurasCity('San Pedro Sula')->create();

    $payload = listingPayload($sanPedroSula);

    expect($payload)->not->toHaveKey('location_id');

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), $payload)
        ->assertSessionHasNoErrors();

    expect(Property::query()->sole()->location_id)->toBe($sanPedroSula->id);
});

test('a pin that matches no supported city is rejected', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $unsavedCity = Location::factory()->hondurasCity()->make();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($unsavedCity))
        ->assertSessionHasErrors('location_id');
});

test('a listing does not need a description', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'description' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect(Property::query()->sole()->description)->toBeNull();
});

test('publishing a listing without photos saves it as a draft instead', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => [],
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();

    expect($property->status)->toBe(ListingStatus::Draft)
        ->and($property->published_at)->toBeNull();
});

test('an approximate location keeps the pin the publisher chose', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity('San Pedro Sula')->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'radius',
            'approximate_radius_km' => 0.3,
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();
    $point = DB::table('properties')
        ->where('id', $property->id)
        ->selectRaw('ST_Y(coordinates::geometry) latitude, ST_X(coordinates::geometry) longitude')
        ->first();

    expect($property->public_location_precision)->toBe(LocationPrecision::Approximate)
        ->and($property->approximate_shape->value)->toBe('radius')
        ->and($property->approximate_radius_meters)->toBe(300)
        ->and((float) $point->latitude)->toBe(15.5057)
        ->and((float) $point->longitude)->toBe(-88.025);
});

test('a custom approximate polygon is validated and persisted', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();
    $polygon = [
        'type' => 'Polygon',
        'coordinates' => [[
            [-87.22, 14.05],
            [-87.16, 14.05],
            [-87.18, 14.10],
            [-87.22, 14.05],
        ]],
    ];

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'polygon',
            'approximate_polygon' => json_encode($polygon, JSON_THROW_ON_ERROR),
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();

    expect($property->approximate_shape->value)->toBe('polygon')
        ->and($property->approximate_radius_meters)->toBeNull()
        ->and($property->approximate_polygon)->toBe($polygon);
});

test('a custom approximate polygon must be closed and stay inside Honduras', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'polygon',
            'approximate_polygon' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[
                    [-87.22, 14.05],
                    [-87.16, 14.05],
                    [-82.00, 14.10],
                    [-87.18, 14.10],
                ]],
            ], JSON_THROW_ON_ERROR),
        ]))
        ->assertSessionHasErrors([
            'approximate_polygon',
            'approximate_polygon.coordinates.0.2.0',
        ]);
});

test('an exact location requires a map pin inside Honduras', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Exact->value,
            'latitude' => null,
            'longitude' => null,
        ]))
        ->assertSessionHasErrors(['latitude', 'longitude']);
});

test('an approximate radius only accepts 100 meter steps from 100 meters to 500 meters', function (float $radius) {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'radius',
            'approximate_radius_km' => $radius,
        ]))
        ->assertSessionHasErrors('approximate_radius_km');
})->with([0.05, 0.15, 0.6, 25]);

/**
 * A submission pinned at the given city's center. The city itself is never
 * submitted — `SaveListingRequest` derives it from the pin — so pinning there is
 * what files the listing under `$location`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function listingPayload(Location $location, array $overrides = []): array
{
    $center = HondurasCityCoordinates::for($location->name);

    return array_replace([
        'name' => 'Casa moderna en Tegucigalpa',
        'type' => 'house',
        'listing_type' => 'rent',
        'status' => 'draft',
        'price_amount' => 22_000,
        'currency' => 'HNL',
        'deposit_amount' => 22_000,
        'utilities_included' => false,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 2,
        'interior_area_m2' => 180,
        'lot_area_m2' => 300,
        'year_built' => 2022,
        'furnishing' => 'unfurnished',
        'description' => 'Casa amplia y segura, cerca de comercios, escuelas y las principales vías de la ciudad.',
        'address_line' => 'Colonia Palmira, Tegucigalpa',
        'location_mode' => 'exact',
        'latitude' => $center?->latitude,
        'longitude' => $center?->longitude,
        'approximate_shape' => null,
        'approximate_radius_km' => null,
        'approximate_polygon' => null,
    ], $overrides);
}
