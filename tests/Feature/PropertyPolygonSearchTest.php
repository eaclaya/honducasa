<?php

use App\Data\GeoPoint;
use App\Data\GeoPolygon;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;

test('properties inside a drawn polygon are returned, outside are excluded', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->create();

    // A square roughly around Tegucigalpa.
    $polygon = new GeoPolygon([
        [-87.25, 14.02],
        [-87.10, 14.02],
        [-87.10, 14.15],
        [-87.25, 14.15],
        [-87.25, 14.02],
    ]);

    $inside = Property::factory()
        ->for($user->currentTeam)
        ->for($user, 'creator')
        ->for($location)
        ->at(new GeoPoint(latitude: 14.0723, longitude: -87.1921))
        ->create();

    Property::factory()
        ->for($user->currentTeam)
        ->for($user, 'creator')
        ->for($location)
        ->at(new GeoPoint(latitude: 15.5000, longitude: -88.0333))
        ->create();

    $results = Property::query()->withinPolygon($polygon)->get();

    expect($results->modelKeys())->toBe([$inside->id]);
});

test('a polygon must be a closed ring of at least 3 vertices', function (array $points) {
    expect(fn () => new GeoPolygon($points))->toThrow(InvalidArgumentException::class);
})->with([
    'fewer than 4 points' => [[[-87.25, 14.02], [-87.10, 14.02], [-87.25, 14.02]]],
    'first and last point do not match' => [[[-87.25, 14.02], [-87.10, 14.02], [-87.10, 14.15], [-87.25, 14.14]]],
]);
