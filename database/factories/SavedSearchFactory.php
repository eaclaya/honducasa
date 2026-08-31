<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use App\Support\SavedSearchFilters;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'filters' => ['location' => 'Tegucigalpa', 'listing_type' => 'rent'],
            'fingerprint' => fn (array $attributes): string => SavedSearchFilters::fingerprint($attributes['filters']),
            'alerts_enabled' => true,
            'last_notified_at' => null,
        ];
    }
}
