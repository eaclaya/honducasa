<?php

use App\Data\GeoPoint;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('PostGIS is enabled with a spatial property index', function () {
    $postgisVersion = DB::scalar('SELECT PostGIS_Version()');
    $indexDefinition = DB::scalar(<<<'SQL'
        SELECT indexdef
        FROM pg_indexes
        WHERE tablename = 'properties'
          AND indexname = 'properties_coordinates_gist'
        SQL);

    expect($postgisVersion)
        ->toBeString()
        ->and($indexDefinition)
        ->toContain('USING gist (coordinates)');
});

test('properties can be filtered and ordered within a radius', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create();
    $origin = new GeoPoint(latitude: 14.0723, longitude: -87.1921);

    $nearest = Property::factory()
        ->for($user->currentTeam)
        ->for($user, 'creator')
        ->for($location)
        ->at(new GeoPoint(latitude: 14.0730, longitude: -87.1921))
        ->create();

    $nearby = Property::factory()
        ->for($user->currentTeam)
        ->for($user, 'creator')
        ->for($location)
        ->at(new GeoPoint(latitude: 14.0900, longitude: -87.1921))
        ->create();

    Property::factory()
        ->for($user->currentTeam)
        ->for($user, 'creator')
        ->for($location)
        ->at(new GeoPoint(latitude: 15.5000, longitude: -88.0333))
        ->create();

    $results = Property::query()
        ->withinRadius($origin, 5_000)
        ->get();

    expect($results->modelKeys())
        ->toBe([$nearest->id, $nearby->id])
        ->and((float) $results->first()->getAttribute('distance_meters'))
        ->toBeGreaterThan(0)
        ->toBeLessThan(1_000);
});

test('radius searches enforce the supported range', function (int $radius) {
    expect(fn () => Property::query()->withinRadius(new GeoPoint(14.0723, -87.1921), $radius))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero' => 0,
    'above maximum' => 50_001,
]);

test('exact property coordinates and address are hidden from serialization', function () {
    $property = Property::factory()->create();

    expect($property->toArray())
        ->not->toHaveKeys(['address_line', 'coordinates'])
        ->and($property->team_id)->toBe($property->creator->current_team_id);
});
