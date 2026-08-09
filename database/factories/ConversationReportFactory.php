<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationReport>
 */
class ConversationReportFactory extends Factory
{
    protected $model = ConversationReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->randomElement(['spam', 'harassment', 'fraud', 'contact_sharing', 'other']),
            'details' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }
}
