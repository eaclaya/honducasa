<?php

namespace App\Support;

use App\Data\GeoPoint;

class HondurasCityCoordinates
{
    /** @var array<string, array{latitude: float, longitude: float}> */
    private const array CENTERS = [
        'Choloma' => ['latitude' => 15.6144, 'longitude' => -87.9530],
        'Choluteca' => ['latitude' => 13.3003, 'longitude' => -87.1908],
        'Comayagua' => ['latitude' => 14.4514, 'longitude' => -87.6375],
        'Danlí' => ['latitude' => 14.0333, 'longitude' => -86.5833],
        'El Progreso' => ['latitude' => 15.4000, 'longitude' => -87.8000],
        'La Ceiba' => ['latitude' => 15.7597, 'longitude' => -86.7822],
        'Puerto Cortés' => ['latitude' => 15.8256, 'longitude' => -87.9297],
        'Roatán' => ['latitude' => 16.3167, 'longitude' => -86.5500],
        'San Pedro Sula' => ['latitude' => 15.5057, 'longitude' => -88.0250],
        'Santa Rosa de Copán' => ['latitude' => 14.7667, 'longitude' => -88.7833],
        'Tegucigalpa' => ['latitude' => 14.0723, 'longitude' => -87.1921],
        'Tela' => ['latitude' => 15.7833, 'longitude' => -87.4500],
    ];

    public static function for(string $city): ?GeoPoint
    {
        $center = self::CENTERS[$city] ?? null;

        return $center === null
            ? null
            : new GeoPoint($center['latitude'], $center['longitude']);
    }
}
