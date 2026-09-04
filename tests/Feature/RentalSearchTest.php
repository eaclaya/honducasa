<?php

use App\Data\GeoPoint;
use App\Enums\Furnishing;
use App\Enums\LocationPrecision;
use App\Enums\LocationType;
use App\Enums\PropertyType;
use App\Models\Location;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('rental results indicate when the current search is saved', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    SavedSearch::factory()->for($user)->create([
        'name' => 'Tegucigalpa rentals',
        'filters' => ['location' => 'Tegucigalpa', 'listing_type' => 'rent'],
    ]);

    $this->actingAs($user)->get(route('rentals.index', [
        'listing_type' => 'rent',
        'location' => 'Tegucigalpa',
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('isSearchSaved', true)
        ->where('savedSearch.id', SavedSearch::query()->sole()->id)
        ->where('savedSearch.hasChanges', false));

    auth()->logout();

    $this->get(route('rentals.index', [
        'listing_type' => 'rent',
        'location' => 'Tegucigalpa',
    ]))->assertInertia(fn (Assert $page) => $page->where('isSearchSaved', false));
});

test('rental pagination uses a compact page link window', function () {
    Property::factory()->count(216)->create();

    $this->get(route('rentals.index', ['page' => 6]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.current_page', 6)
            ->where('properties.last_page', 12)
            ->where('properties.links', function (Collection $links): bool {
                $labels = $links->pluck('label');

                return $links->count() <= 11
                    && $labels->contains('5')
                    && $labels->contains('6')
                    && $labels->contains('7')
                    && ! $labels->contains('4');
            }));
});

test('rental results track refinements made from a saved search', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $savedSearch = SavedSearch::factory()->for($user)->create();

    $this->actingAs($user)->get(route('rentals.index', [
        ...$savedSearch->filters,
        'saved_search' => $savedSearch->id,
        'bedrooms' => 3,
        'property_type' => 'house',
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('isSearchSaved', false)
        ->where('savedSearch.id', $savedSearch->id)
        ->where('savedSearch.name', $savedSearch->name)
        ->where('savedSearch.hasChanges', true));
});

test('rentals can be searched by Honduran city', function () {
    Storage::fake('public');
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

    $capitalApartment = Property::factory()->for($tegucigalpa)->create([
        'name' => 'Capital Apartment',
        'price_amount' => 15_000,
        'currency' => 'HNL',
    ]);
    $media = $capitalApartment
        ->addMedia(UploadedFile::fake()->image('capital-apartment.jpg'))
        ->toMediaCollection('photos');
    $secondMedia = $capitalApartment
        ->addMedia(UploadedFile::fake()->image('capital-apartment-patio.jpg'))
        ->toMediaCollection('photos');
    Property::factory()->for($sanPedroSula)->create(['name' => 'Valley House']);

    $this->get(route('rentals.index', ['location' => 'tegucigalpa']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rentals/Index')
            ->where('filters.location', 'tegucigalpa')
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Capital Apartment')
            ->where('properties.data.0.location', 'Tegucigalpa')
            ->where('properties.data.0.priceAmount', 15_000)
            ->where('properties.data.0.currency', 'HNL')
            ->where('properties.data.0.primaryImage.url', $media->getUrl('thumb'))
            ->where('properties.data.0.images.0.url', $media->getUrl('thumb'))
            ->where('properties.data.0.images.1.url', $secondMedia->getUrl('thumb')));
});

test('location searches do not require accent marks', function () {
    $danli = Location::factory()->create([
        'name' => 'Danlí',
        'slug' => 'danli',
        'type' => LocationType::City,
    ]);

    Property::factory()->for($danli)->create(['name' => 'Danlí apartment']);

    foreach (['danli', 'danlí'] as $location) {
        $this->get(route('rentals.index', ['location' => $location]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('properties.data', 1)
                ->where('properties.data.0.name', 'Danlí apartment')
                ->where('properties.data.0.location', 'Danlí'));
    }
});

test('rentals can be searched by an administrative parent location', function () {
    $department = Location::factory()->create([
        'name' => 'El Paraíso',
        'slug' => 'el-paraiso',
        'type' => LocationType::Department,
    ]);
    $municipality = Location::factory()->for($department, 'parent')->create([
        'name' => 'Danlí',
        'slug' => 'danli',
        'type' => LocationType::Municipality,
    ]);
    $city = Location::factory()->for($municipality, 'parent')->create([
        'name' => 'Ciudad de Danlí',
        'slug' => 'ciudad-de-danli',
        'type' => LocationType::City,
    ]);
    Property::factory()->for($city)->create(['name' => 'Home in El Paraíso']);

    $this->get(route('rentals.index', ['location' => 'El Paraiso']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Home in El Paraíso'));
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

test('rentals can be filtered by price features and furnishing', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);

    Property::factory()->for($location)->create([
        'name' => 'Matching apartment',
        'currency' => 'HNL',
        'price_amount' => 18_000,
        'bedrooms' => 3,
        'bathrooms' => 2.5,
        'parking_spaces' => 2,
        'interior_area_m2' => 140,
        'furnishing' => Furnishing::Furnished,
        'utilities_included' => true,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'Non-matching apartment',
        'currency' => 'HNL',
        'price_amount' => 12_000,
        'bedrooms' => 1,
        'bathrooms' => 1,
        'parking_spaces' => 0,
        'interior_area_m2' => 60,
        'furnishing' => Furnishing::Unfurnished,
        'utilities_included' => false,
    ]);

    $this->get(route('rentals.index', [
        'currency' => 'HNL',
        'min_price' => 15_000,
        'max_price' => 20_000,
        'bedrooms' => 2,
        'bathrooms' => 2,
        'parking_spaces' => 1,
        'min_area' => 100,
        'max_area' => 180,
        'furnishing' => Furnishing::Furnished->value,
        'utilities_included' => true,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('properties.data', 1)
        ->where('properties.data.0.name', 'Matching apartment')
        ->where('filters.minPrice', '15000')
        ->where('filters.utilitiesIncluded', true));
});

test('price filters compare listings across currencies using the normalized price', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);

    Property::factory()->for($location)->create([
        'name' => 'USD apartment',
        'currency' => 'USD',
        'price_amount' => 1_000,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'Below range HNL apartment',
        'currency' => 'HNL',
        'price_amount' => 23_000,
    ]);

    $this->get(route('rentals.index', [
        'location' => 'Tegucigalpa',
        'min_price' => 24_000,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('properties.data', 1)
        ->where('properties.data.0.name', 'USD apartment')
        ->where('properties.data.0.priceAmount', 24_700)
        ->where('properties.data.0.currency', 'HNL')
        ->where('properties.data.0.originalPriceAmount', 1_000)
        ->where('properties.data.0.originalCurrency', 'USD')
        ->where('properties.data.0.priceIsConverted', true));
});

test('price sorting compares the normalized value instead of raw currency amounts', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);

    Property::factory()->for($location)->create([
        'name' => 'USD apartment',
        'currency' => 'USD',
        'price_amount' => 1_000,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'HNL apartment',
        'currency' => 'HNL',
        'price_amount' => 24_000,
    ]);

    $this->get(route('rentals.index', ['sort' => 'price_asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.name', 'HNL apartment')
            ->where('properties.data.1.name', 'USD apartment'));
});

test('rentals can be sorted by price', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);

    Property::factory()->for($location)->create(['name' => 'Expensive', 'price_amount' => 25_000]);
    Property::factory()->for($location)->create(['name' => 'Affordable', 'price_amount' => 8_000]);

    $this->get(route('rentals.index', ['sort' => 'price_asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.name', 'Affordable')
            ->where('properties.data.1.name', 'Expensive')
            ->where('filters.sort', 'price_asc'));
});

test('a maximum price or area without a minimum still searches', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->create([
        'name' => 'Cheap and small',
        'type' => PropertyType::House,
        'currency' => 'HNL',
        'price_amount' => 5_000,
        'interior_area_m2' => 40,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'Pricey and large',
        'type' => PropertyType::House,
        'currency' => 'HNL',
        'price_amount' => 50_000,
        'interior_area_m2' => 400,
    ]);

    $this->get(route('rentals.index', ['max_price' => 10_000]))
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Cheap and small'));

    $this->get(route('rentals.index', ['max_area' => 100]))
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Cheap and small'));
});

test('area filters use lot area for land and preserve the square vara display unit', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->create([
        'name' => 'Matching land',
        'type' => PropertyType::Land,
        'interior_area_m2' => null,
        'lot_area_m2' => 700,
    ]);
    Property::factory()->for($location)->create([
        'name' => 'Small land',
        'type' => PropertyType::Land,
        'interior_area_m2' => null,
        'lot_area_m2' => 300,
    ]);

    $this->get(route('rentals.index', [
        'property_type' => PropertyType::Land->value,
        'min_area' => 650,
        'max_area' => 750,
        'area_unit' => 'vara2',
    ]))->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Matching land')
            ->where('filters.minArea', '650')
            ->where('filters.maxArea', '750')
            ->where('filters.areaUnit', 'vara2'));
});

test('rental filter ranges and options are validated', function () {
    $this->get(route('rentals.index', [
        'min_price' => 20_000,
        'max_price' => 10_000,
        'min_area' => 200,
        'max_area' => 100,
        'sort' => 'distance',
    ]))->assertSessionHasErrors(['max_price', 'max_area', 'sort']);
});

test('rentals can be filtered to the visible map bounds', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.08, -87.20))->create(['name' => 'Inside map']);
    Property::factory()->for($location)->at(new GeoPoint(15.50, -88.03))->create(['name' => 'Outside map']);

    $this->get(route('rentals.index', [
        'west' => -87.30,
        'south' => 14.00,
        'east' => -87.10,
        'north' => 14.20,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('properties.data', 1)
        ->where('properties.data.0.name', 'Inside map')
        ->where('filters.west', -87.30)
        ->where('filters.north', 14.20));
});

test('exact listing map coordinates preserve their full precision', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.076543, -87.192345))->create([
        'public_location_precision' => LocationPrecision::Exact,
    ]);

    $this->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.mapLatitude', 14.076543)
            ->where('properties.data.0.mapLongitude', -87.192345));
});

test('approximate listing map coordinates remain rounded for public privacy', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.076543, -87.192345))->create([
        'public_location_precision' => LocationPrecision::Approximate,
    ]);

    $this->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.mapLatitude', 14.08)
            ->where('properties.data.0.mapLongitude', -87.19));
});

test('map bounds must be ordered and inside Honduras', function () {
    $this->get(route('rentals.index', [
        'west' => -87.10,
        'south' => 14.20,
        'east' => -87.30,
        'north' => 14.00,
    ]))->assertSessionHasErrors(['east', 'north']);
});

test('rentals can be filtered to a drawn polygon area', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.08, -87.20))->create(['name' => 'Inside area']);
    Property::factory()->for($location)->at(new GeoPoint(15.50, -88.03))->create(['name' => 'Outside area']);

    $polygon = [
        [-87.30, 14.01],
        [-87.10, 14.01],
        [-87.10, 14.20],
        [-87.30, 14.20],
        [-87.30, 14.01],
    ];

    $this->get(route('rentals.index', ['polygon' => encodedPolygon($polygon)]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Inside area')
            ->where('filters.polygon', $polygon));
});

test('a polygon search takes precedence over bounds and nearby params submitted alongside it', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.08, -87.20))->create(['name' => 'Inside polygon']);
    Property::factory()->for($location)->at(new GeoPoint(15.50, -88.03))->create(['name' => 'Only inside bounds']);

    $this->get(route('rentals.index', [
        'polygon' => encodedPolygon([
            [-87.30, 14.00],
            [-87.10, 14.00],
            [-87.10, 14.20],
            [-87.30, 14.20],
            [-87.30, 14.00],
        ]),
        'west' => -89.00,
        'south' => 13.00,
        'east' => -87.50,
        'north' => 16.00,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('properties.data', 1)
        ->where('properties.data.0.name', 'Inside polygon'));
});

test('a drawn polygon must have at least four points and must be closed', function () {
    $this->get(route('rentals.index', [
        'polygon' => encodedPolygon([[-87.30, 14.00], [-87.10, 14.00], [-87.30, 14.00]]),
    ]))->assertSessionHasErrors('polygon');

    $this->get(route('rentals.index', [
        'polygon' => encodedPolygon([[-87.30, 14.00], [-87.10, 14.00], [-87.10, 14.20], [-87.30, 14.19]]),
    ]))->assertSessionHasErrors('polygon');
});

test('polygon points must stay inside Honduras', function () {
    $this->get(route('rentals.index', [
        'polygon' => encodedPolygon([[-87.30, 14.00], [-87.10, 14.00], [-87.10, 14.20], [-40.00, 14.00], [-87.30, 14.00]]),
    ]))->assertSessionHasErrors('polygon.3.0');
});

test('rentals can be searched within five kilometers of the users location', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.0730, -87.1921))->create(['name' => 'Nearby home']);
    Property::factory()->for($location)->at(new GeoPoint(14.1523, -87.1921))->create(['name' => 'Distant home']);

    $this->get(route('rentals.index', [
        'latitude' => 14.0723,
        'longitude' => -87.1921,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('properties.data', 1)
        ->where('properties.data.0.name', 'Nearby home')
        ->where('filters.latitude', 14.0723)
        ->where('filters.longitude', -87.1921)
        ->where('filters.radiusMeters', 5_000));
});

test('sorting applies on top of a nearby search instead of being overridden by it', function () {
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    Property::factory()->for($location)->at(new GeoPoint(14.0724, -87.1921))->create([
        'name' => 'Closest, cheapest, oldest',
        'currency' => 'HNL',
        'price_amount' => 8_000,
        'published_at' => now()->subWeek(),
    ]);
    Property::factory()->for($location)->at(new GeoPoint(14.0800, -87.1921))->create([
        'name' => 'Farther, priciest, newest',
        'currency' => 'HNL',
        'price_amount' => 25_000,
        'published_at' => now(),
    ]);

    $nearby = ['latitude' => 14.0723, 'longitude' => -87.1921];

    $this->get(route('rentals.index', [...$nearby, 'sort' => 'price_desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.name', 'Farther, priciest, newest')
            ->where('properties.data.1.name', 'Closest, cheapest, oldest'));

    $this->get(route('rentals.index', [...$nearby, 'sort' => 'price_asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.name', 'Closest, cheapest, oldest')
            ->where('properties.data.1.name', 'Farther, priciest, newest'));

    $this->get(route('rentals.index', [...$nearby, 'sort' => 'newest']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.name', 'Farther, priciest, newest')
            ->where('properties.data.1.name', 'Closest, cheapest, oldest'));
});

test('nearby search coordinates must be supplied together and valid', function () {
    $this->get(route('rentals.index', ['latitude' => 14.0723]))
        ->assertSessionHasErrors(['longitude']);

    $this->get(route('rentals.index', ['latitude' => 91, 'longitude' => -87.1921]))
        ->assertSessionHasErrors(['latitude']);
});

test('published properties from a suspended team do not appear in search results', function () {
    $suspendedTeam = Team::factory()->create(['suspended_at' => now()]);
    Property::factory()->create(['name' => 'Visible home']);
    Property::factory()->create(['name' => 'Hidden home', 'team_id' => $suspendedTeam->id]);

    $this->get(route('rentals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Visible home'));
});
