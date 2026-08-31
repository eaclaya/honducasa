<?php

namespace Database\Factories;

use App\Models\ModerationStrike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationStrike>
 */
class ModerationStrikeFactory extends Factory
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
            'source' => 'listing_text',
            'reason' => 'Automated text moderation flagged listing content.',
            'metadata' => ['fields' => ['description']],
            'cleared_at' => null,
            'cleared_by' => null,
        ];
    }
}
