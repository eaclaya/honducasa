<?php

use App\Models\Location;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('legacy property type values are backfilled to their closest surviving case', function () {
    $location = Location::factory()->create();
    $legacyTypes = [
        'condominium' => 'apartment',
        'townhouse' => 'house',
        'room' => 'apartment',
        'studio' => 'apartment',
    ];

    foreach (array_keys($legacyTypes) as $legacyType) {
        $property = Property::factory()->create(['location_id' => $location->id]);
        DB::table('properties')->where('id', $property->id)->update(['type' => $legacyType]);
    }

    (require database_path('migrations/2026_09_04_181748_backfill_legacy_property_types.php'))->up();

    foreach ($legacyTypes as $legacyType => $expected) {
        expect(DB::table('properties')->where('type', $legacyType)->count())->toBe(0);
    }

    expect(DB::table('properties')->where('type', 'apartment')->count())->toBe(3)
        ->and(DB::table('properties')->where('type', 'house')->count())->toBe(1);
});
