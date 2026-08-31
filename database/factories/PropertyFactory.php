<?php

namespace Database\Factories;

use App\Data\GeoPoint;
use App\Enums\ApproximateLocationShape;
use App\Enums\Furnishing;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->streetName().' '.fake()->randomElement(['House', 'Apartment', 'Residence']);
        $latitude = fake()->latitude(13.9, 14.2);
        $longitude = fake()->longitude(-87.35, -87.05);

        return [
            'created_by' => User::factory()->withPersonalTeam(),
            'team_id' => fn (array $attributes) => User::query()
                ->whereKey($attributes['created_by'])
                ->sole()
                ->current_team_id,
            'location_id' => Location::factory(),
            'type' => fake()->randomElement(PropertyType::cases()),
            'listing_type' => ListingType::Rent,
            'status' => ListingStatus::Published,
            'published_at' => now(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'address_line' => fake()->streetAddress(),
            'address_landmark' => fake()->sentence(6),
            'coordinates' => (new GeoPoint($latitude, $longitude))->toPostgisPoint(),
            'public_location_precision' => LocationPrecision::Approximate,
            'approximate_shape' => ApproximateLocationShape::Radius,
            'approximate_radius_meters' => 1_000,
            'approximate_polygon' => null,
            'bedrooms' => fake()->numberBetween(0, 5),
            'bathrooms' => fake()->randomElement(['1.0', '1.5', '2.0', '2.5', '3.0']),
            'parking_spaces' => fake()->numberBetween(0, 3),
            'interior_area_m2' => fake()->numberBetween(35, 350),
            'lot_area_m2' => fake()->optional()->numberBetween(80, 1_000),
            'year_built' => fake()->optional()->numberBetween(1950, now()->year),
            'furnishing' => fake()->randomElement(Furnishing::cases()),
            'price_amount' => fake()->numberBetween(5_000, 35_000),
            'currency' => 'HNL',
            'normalized_price_amount' => fn (array $attributes) => app(CurrencyConverter::class)->toBase(
                $attributes['price_amount'],
                $attributes['currency'],
            ),
            'normalized_currency' => fn () => app(CurrencyConverter::class)->baseCurrency(),
            'normalization_rate' => fn (array $attributes) => app(CurrencyConverter::class)->rateToBase($attributes['currency']),
            'price_normalized_at' => now(),
            'deposit_amount' => fake()->numberBetween(5_000, 35_000),
            'utilities_included' => fake()->boolean(20),
            'description' => fake()->paragraphs(2, true),
        ];
    }

    /**
     * Place the property at an exact point.
     */
    public function at(GeoPoint $point): static
    {
        return $this->state(fn (array $attributes) => [
            'coordinates' => $point->toPostgisPoint(),
        ]);
    }

    /**
     * Indicate that the property is furnished.
     */
    public function furnished(): static
    {
        return $this->state(fn (array $attributes) => [
            'furnishing' => Furnishing::Furnished,
        ]);
    }
}
