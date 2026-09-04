<?php

use App\Enums\SubscriptionLadder;
use App\Enums\TeamRole;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;

test('a team whose trial expired without subscribing is redirected to billing', function () {
    SubscriptionPlan::factory()->entryTier()->create(['ladder' => SubscriptionLadder::Agency]);
    $user = User::factory()->create();
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('dashboard', $team))
        ->assertRedirect(route('teams.billing.edit', $team));
});

test('a subscribed team can access its dashboard', function () {
    $plan = SubscriptionPlan::factory()->create();
    $user = User::factory()->create();
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);
    $team->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active']);

    $this->actingAs($user)
        ->get(route('dashboard', $team))
        ->assertOk();
});

test('an individual whose trial expired without subscribing is redirected to billing', function () {
    SubscriptionPlan::factory()->entryTier()->create(['ladder' => SubscriptionLadder::Individual]);
    $user = User::factory()->create(['individual_trial_ends_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('personal-listings.index'))
        ->assertRedirect(route('billing.edit'));
});

test('a brand-new individual with no trial started yet keeps access to their listings', function () {
    $user = User::factory()->create(['individual_trial_ends_at' => null]);

    $this->actingAs($user)
        ->get(route('personal-listings.index'))
        ->assertOk();
});
