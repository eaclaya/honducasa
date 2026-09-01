<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'team_id' => fn (array $attributes) => Property::query()->findOrFail($attributes['property_id'])->team_id,
            'renter_id' => User::factory(),
            'status' => 'active',
            'last_message_at' => now(),
        ];
    }
}
