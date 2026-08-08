<?php

namespace Database\Seeders;

use App\Enums\Furnishing;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\LocationPrecision;
use App\Enums\LocationType;
use App\Enums\PropertyType;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoPropertySeeder extends Seeder
{
    public const int PROPERTY_COUNT = 10_000;

    private const int INSERT_CHUNK_SIZE = 500;

    /** @var list<string> */
    private const array IMAGE_URLS = [
        'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600585152915-d208bec867a1?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600566753051-f0b89df2dd90?auto=format&fit=crop&w=1200&q=80',
    ];

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
            ?? User::factory()->withPersonalTeam()->create([
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
                    $listingType = fake()->boolean(78) ? ListingType::Rent : ListingType::Buy;
                    $pricing = $this->listingPricing($market['city'], $type, $listingType);
                    $name = fake()->randomElement(['Casa', 'Apartamento', 'Residencia', 'Condominio', 'Estudio']).' '.fake()->streetName();
                    $timestamp = now();

                    $rows[] = [
                        'team_id' => $teamId,
                        'location_id' => $market['location_id'],
                        'created_by' => $owner->id,
                        'type' => $type->value,
                        'listing_type' => $listingType->value,
                        'status' => ListingStatus::Published->value,
                        'published_at' => $timestamp,
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
                        'price_amount' => $pricing['price_amount'],
                        'currency' => $pricing['currency'],
                        'deposit_amount' => $listingType === ListingType::Rent && fake()->boolean(85) ? $pricing['price_amount'] : null,
                        'utilities_included' => $listingType === ListingType::Rent && fake()->boolean(18),
                        'description' => fake()->paragraphs(2, true),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                DB::table('properties')->insert($rows);
            }

            $this->seedPrimaryImages();
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

    /**
     * @return array{price_amount: int, currency: string}
     */
    private function listingPricing(string $city, PropertyType $type, ListingType $listingType): array
    {
        $baseRent = match ($type) {
            PropertyType::Room => 4_500,
            PropertyType::Studio => 7_000,
            PropertyType::Apartment => 11_000,
            PropertyType::Townhouse => 14_000,
            PropertyType::House => 15_500,
            PropertyType::Condominium => 18_000,
        };

        if ($city === 'Roatán') {
            $rent = max(450, (int) round(($baseRent / 24.5) * fake()->randomFloat(2, 0.85, 1.8)));

            return [
                'price_amount' => $listingType === ListingType::Rent ? $rent : $rent * fake()->numberBetween(140, 220),
                'currency' => 'USD',
            ];
        }

        $cityMultiplier = match ($city) {
            'Tegucigalpa' => 1.2,
            'San Pedro Sula' => 1.15,
            'Puerto Cortés', 'La Ceiba' => 1.05,
            default => 0.85,
        };

        $rent = (int) (round(($baseRent * $cityMultiplier * fake()->randomFloat(2, 0.75, 1.45)) / 250) * 250);

        return [
            'price_amount' => $listingType === ListingType::Rent ? $rent : $rent * fake()->numberBetween(140, 220),
            'currency' => 'HNL',
        ];
    }

    private function seedPrimaryImages(): void
    {
        Property::query()
            ->with('location:id,name')
            ->where('slug', 'like', 'demo-%')
            ->select(['id', 'location_id', 'name', 'slug'])
            ->chunkById(self::INSERT_CHUNK_SIZE, function (Collection $properties): void {
                $timestamp = now();
                $rows = $properties->flatMap(function (Property $property) use ($timestamp): array {
                    $startIndex = abs(crc32($property->slug)) % count(self::IMAGE_URLS);

                    return array_map(fn (int $sortOrder) => [
                        'property_id' => $property->id,
                        'url' => self::IMAGE_URLS[($startIndex + $sortOrder) % count(self::IMAGE_URLS)],
                        'alt_text' => ($property->name ?? 'Rental property').' in '.$property->location->name,
                        'sort_order' => $sortOrder,
                        'is_primary' => $sortOrder === 0,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ], range(0, 2));
                })->all();

                DB::table('property_images')->insert($rows);
            });
    }
}
