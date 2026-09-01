<?php

use App\Data\GeoPoint;

test('it formats a PostGIS point with longitude first', function () {
    $point = new GeoPoint(latitude: 14.0723, longitude: -87.1921);

    expect($point->toPostgisPoint())->toBe('SRID=4326;POINT(-87.19210000 14.07230000)');
});

test('it rejects invalid coordinates', function (float $latitude, float $longitude) {
    expect(fn () => new GeoPoint($latitude, $longitude))->toThrow(InvalidArgumentException::class);
})->with([
    'latitude below range' => [-90.1, 0.0],
    'latitude above range' => [90.1, 0.0],
    'longitude below range' => [0.0, -180.1],
    'longitude above range' => [0.0, 180.1],
    'infinite latitude' => [INF, 0.0],
]);
