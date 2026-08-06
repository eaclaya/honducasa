<?php

namespace Database\Seeders;

use App\Enums\Furnishing;
use App\Enums\LocationPrecision;
use App\Enums\LocationType;
use App\Enums\PropertyType;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoPropertySeeder extends Seeder
{
    public const int PROPERTY_COUNT = 10_000;

    private const int INSERT_CHUNK_SIZE = 500;

    /**
     * Representative rental markets and their approximate city centers.
     *
     * @var list<array{city: string, department: string, latitude: float, longitude: float, radius_km: int, weight: int}>
     */
    private const array MARKETS = [
        ['city' => 'Tegucigalpa', 'department' => 'Francisco Morazán', 'latitude' => 14.0723, 'longitude' => -87.1921, 'radius_km' => 12, 'weight' => 28],
        ['city' => 'San Pedro Sula', 'department' => 'Cortés', 'latitude' => 15.5007, 'longitude' => -88.0250, 'radius_km' => 11, 'weight' => 22],
        ['city' => 'La Ceiba', 'department' => 'Atlántida', 'latitude' => 15.7597, 'longitude' => -86.7822, 'radius_km' => 8, 'weight' => 9],
        ['city' => 'Choloma', 'department' => 'Cortés', 'latitude' => 15.6144, 'longitude' => -87.9530, 'radius_km' => 7, 'weight' => 7],
        ['city' => 'El Progreso', 'department' => 'Yoro', 'latitude' => 15.4000, 'longitude' => -87.8000, 'radius_km' => 7, 'weight' => 6],
        ['city' => 'Comayagua', 'department' => 'Comayagua', 'latitude' => 14.4514, 'longitude' => -87.6375, 'radius_km' => 7, 'weight' => 6],
        ['city' => 'Puerto Cortés', 'department' => 'Cortés', 'latitude' => 15.8256, 'longitude' => -87.9297, 'radius_km' => 6, 'weight' => 5],
        ['city' => 'Roatán', 'department' => 'Islas de la Bahía', 'latitude' => 16.3167, 'longitude' => -86.5333, 'radius_km' => 8, 'weight' => 5],
        ['city' => 'Danlí', 'department' => 'El Paraíso', 'latitude' => 14.0333, 'longitude' => -86.5833, 'radius_km' => 6, 'weight' => 4],
        ['city' => 'Choluteca', 'department' => 'Choluteca', 'latitude' => 13.3010, 'longitude' => -87.1908, 'radius_km' => 6, 'weight' => 4],
        ['city' => 'Santa Rosa de Copán', 'department' => 'Copán', 'latitude' => 14.7667, 'longitude' => -88.7792, 'radius_km' => 5, 'weight' => 2],
        ['city' => 'Tela', 'department' => 'Atlántida', 'latitude' => 15.7833, 'longitude' => -87.4500, 'radius_km' => 5, 'weight' => 2],
    ];

    /**
     * Seed demo rental inventory across Honduras.
     */
    public function run(): void
    {
        $this->call(LocationSeeder::class);

        $owner = User::query()->where('email', 'demo@honducasa.test')->first()
            ?? User::factory()->create([
                'name' => 'HonduCasa Demo',
                'email' => 'demo@honducasa.test',
            ]);

        $teamId = $owner->current_team_id;

        if ($teamId === null) {
            throw new \RuntimeException('The demo property owner must have a current team.');
        }

        $markets = $this->marketsWithLocationIds();

        DB::transaction(function () use ($markets, $owner, $teamId): void {
            Property::query()->where('slug', 'like', 'demo-%')->forceDelete();

            for ($offset = 0; $offset < self::PROPERTY_COUNT; $offset += self::INSERT_CHUNK_SIZE) {
                $rows = [];
                $count = min(self::INSERT_CHUNK_SIZE, self::PROPERTY_COUNT - $offset);

                for ($index = 0; $index < $count; $index++) {
                    $market = $this->randomMarket($markets);
                    [$latitude, $longitude] = $this->randomPointNear($market);
                    $type = fake()->randomElement(PropertyType::cases());
                    $name = fake()->randomElement(['Casa', 'Apartamento', 'Residencia', 'Condominio', 'Estudio']).' '.fake()->streetName();
                    $timestamp = now();

                    $rows[] = [
                        'team_id' => $teamId,
                        'location_id' => $market['location_id'],
                        'created_by' => $owner->id,
                        'type' => $type->value,
                        'name' => $name,
                        'slug' => 'demo-'.Str::slug($market['city']).'-'.Str::lower(Str::random(12)),
                        'address_line' => fake()->streetAddress(),
                        'address_landmark' => fake()->optional(0.75)->sentence(6),
                        'coordinates' => "SRID=4326;POINT({$longitude} {$latitude})",
                        'public_location_precision' => fake()->randomElement(LocationPrecision::cases())->value,
                        'bedrooms' => fake()->numberBetween(0, 5),
                        'bathrooms' => fake()->randomElement(['1.0', '1.5', '2.0', '2.5', '3.0', '3.5']),
                        'parking_spaces' => fake()->numberBetween(0, 3),
                        'interior_area_m2' => fake()->numberBetween(28, 350),
                        'lot_area_m2' => fake()->optional(0.6)->numberBetween(75, 1_200),
                        'year_built' => fake()->optional(0.85)->numberBetween(1960, now()->year),
                        'furnishing' => fake()->randomElement(Furnishing::cases())->value,
                        'description' => fake()->paragraphs(2, true),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                DB::table('properties')->insert($rows);
            }
        });

        $this->command->info(self::PROPERTY_COUNT.' demo properties seeded across '.count(self::MARKETS).' Honduran markets.');
    }

    /**
     * @return list<array{city: string, department: string, latitude: float, longitude: float, radius_km: int, weight: int, location_id: int}>
     */
    private function marketsWithLocationIds(): array
    {
        $country = Location::query()
            ->where('country_code', 'HN')
            ->where('type', LocationType::Country)
            ->where('slug', 'honduras')
            ->sole();

        return array_map(function (array $market) use ($country): array {
            $department = Location::query()->updateOrCreate(
                [
                    'country_code' => 'HN',
                    'type' => LocationType::Department,
                    'parent_id' => $country->id,
                    'slug' => Str::slug($market['department']),
                ],
                ['name' => $market['department'], 'is_active' => true],
            );

            $municipality = Location::query()->updateOrCreate(
                [
                    'country_code' => 'HN',
                    'type' => LocationType::Municipality,
                    'parent_id' => $department->id,
                    'slug' => Str::slug($market['city']),
                ],
                ['name' => $market['city'], 'is_active' => true],
            );

            $city = Location::query()->updateOrCreate(
                [
                    'country_code' => 'HN',
                    'type' => LocationType::City,
                    'parent_id' => $municipality->id,
                    'slug' => Str::slug($market['city']),
                ],
                ['name' => $market['city'], 'is_active' => true],
            );

            return [...$market, 'location_id' => $city->id];
        }, self::MARKETS);
    }

    /**
     * @param  list<array{city: string, department: string, latitude: float, longitude: float, radius_km: int, weight: int, location_id: int}>  $markets
     * @return array{city: string, department: string, latitude: float, longitude: float, radius_km: int, weight: int, location_id: int}
     */
    private function randomMarket(array $markets): array
    {
        if ($markets === []) {
            throw new \RuntimeException('At least one demo rental market is required.');
        }

        $fallback = $markets[0];
        $selection = fake()->numberBetween(1, array_sum(array_column($markets, 'weight')));

        foreach ($markets as $market) {
            $fallback = $market;
            $selection -= $market['weight'];

            if ($selection <= 0) {
                return $market;
            }
        }

        return $fallback;
    }

    /**
     * @param  array{latitude: float, longitude: float, radius_km: int}  $market
     * @return array{float, float}
     */
    private function randomPointNear(array $market): array
    {
        $distanceKm = sqrt(fake()->randomFloat(6, 0, 1)) * $market['radius_km'];
        $angle = fake()->randomFloat(6, 0, 2 * M_PI);
        $latitude = $market['latitude'] + (($distanceKm * cos($angle)) / 111.32);
        $longitude = $market['longitude'] + (($distanceKm * sin($angle)) / (111.32 * cos(deg2rad($market['latitude']))));

        return [round($latitude, 7), round($longitude, 7)];
    }
}
