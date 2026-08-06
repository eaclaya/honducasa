<?php

namespace Database\Factories;

use App\Enums\IdentityProvider;
use App\Models\OauthIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OauthIdentity>
 */
class OauthIdentityFactory extends Factory
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
            'provider' => IdentityProvider::Google,
            'provider_subject' => fake()->unique()->numerify('#####################'),
            'provider_email' => fake()->unique()->safeEmail(),
            'linked_at' => now(),
            'last_used_at' => null,
        ];
    }
}
