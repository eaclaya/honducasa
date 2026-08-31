<?php

namespace Database\Factories;

use App\Models\ListingPhotoEnhancement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingPhotoEnhancement>
 */
class ListingPhotoEnhancementFactory extends Factory
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
            'property_id' => null,
            'draft_key' => (string) $this->faker->uuid(),
            'media_id' => null,
        ];
    }

    /**
     * Usage already booked against a saved listing rather than a wizard draft.
     */
    public function forListing(Property $listing): static
    {
        return $this->state(fn () => [
            'property_id' => $listing->getKey(),
            'draft_key' => null,
        ]);
    }
}
