<?php

namespace Database\Seeders;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $honduras = Location::query()->updateOrCreate(
            ['country_code' => 'HN', 'type' => LocationType::Country, 'slug' => 'honduras'],
            ['name' => 'Honduras', 'is_active' => true],
        );

        $franciscoMorazan = Location::query()->updateOrCreate(
            [
                'country_code' => 'HN',
                'type' => LocationType::Department,
                'parent_id' => $honduras->id,
                'slug' => 'francisco-morazan',
            ],
            ['name' => 'Francisco Morazán', 'is_active' => true],
        );

        $distritoCentral = Location::query()->updateOrCreate(
            [
                'country_code' => 'HN',
                'type' => LocationType::Municipality,
                'parent_id' => $franciscoMorazan->id,
                'slug' => 'distrito-central',
            ],
            ['name' => 'Distrito Central', 'is_active' => true],
        );

        Location::query()->updateOrCreate(
            [
                'country_code' => 'HN',
                'type' => LocationType::City,
                'parent_id' => $distritoCentral->id,
                'slug' => 'tegucigalpa',
            ],
            ['name' => 'Tegucigalpa', 'is_active' => true],
        );
    }
}
