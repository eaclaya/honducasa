<?php

use App\Enums\ListingType;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\DemoPropertySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('it provisions and attributes demo listings to the configured superadmin', function () {
    $this->seed(DemoPropertySeeder::class);

    $superadmin = User::query()->where('email', config('app.superadmin_email'))->sole();

    expect($superadmin->is_admin)->toBeTrue();
    expect(Property::query()->where('slug', 'like', 'demo-%')->pluck('created_by')->unique()->all())
        ->toBe([$superadmin->id]);
});

test('it seeds ten thousand demo properties inside Honduras', function () {
    $this->seed(DemoPropertySeeder::class);

    expect(Property::query()->where('slug', 'like', 'demo-%')->count())
        ->toBe(DemoPropertySeeder::PROPERTY_COUNT)
        ->and(DB::table('media')->where('collection_name', 'photos')->where('order_column', 1)->count())
        ->toBe(DemoPropertySeeder::PROPERTY_COUNT)
        ->and(DB::table('media')->where('collection_name', 'photos')->count())
        ->toBe(DemoPropertySeeder::PROPERTY_COUNT * 3)
        ->and(Property::query()->distinct()->count('location_id'))
        ->toBe(12)
        ->and(Property::query()->where('listing_type', ListingType::Rent)->exists())
        ->toBeTrue()
        ->and(Property::query()->where('listing_type', ListingType::Buy)->exists())
        ->toBeTrue();

    $outsideHonduras = DB::table('properties')
        ->whereRaw('ST_Y(coordinates::geometry) NOT BETWEEN 12.9 AND 16.6')
        ->orWhereRaw('ST_X(coordinates::geometry) NOT BETWEEN -89.4 AND -83.1')
        ->count();

    expect($outsideHonduras)->toBe(0);
});

test('rerunning the seeder replaces demo inventory instead of duplicating it', function () {
    $this->seed(DemoPropertySeeder::class);
    $this->seed(DemoPropertySeeder::class);

    expect(Property::query()->where('slug', 'like', 'demo-%')->count())
        ->toBe(DemoPropertySeeder::PROPERTY_COUNT);
});
