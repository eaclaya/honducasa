<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'parent_id' => null,
            'country_code' => 'HN',
            'type' => LocationType::City,
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }

    /**
     * A Honduran city we know the center of, so listings pinned near it can be
     * filed under it (see `App\Support\NearestCity`).
     */
    public function hondurasCity(string $name = 'Tegucigalpa'): static
    {
        return $this->state(fn (array $attributes): array => [
            'country_code' => 'HN',
            'type' => LocationType::City,
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }

    /**
     * Indicate that the location is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
