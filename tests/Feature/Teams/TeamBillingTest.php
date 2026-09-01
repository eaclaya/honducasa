<?php

use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamSubscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a team owner can view the billing page with plans for their ladder', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    SubscriptionPlan::factory()->create(['ladder' => 'individual', 'name' => 'Individual — Basic']);
    SubscriptionPlan::factory()->create(['ladder' => 'individual', 'name' => 'Individual — Plus']);
    SubscriptionPlan::factory()->create(['ladder' => 'agency', 'name' => 'Agency — Starter']);

    $this->actingAs($user)
        ->get(route('teams.billing.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Billing')
            ->has('plans', 2));
});

test('inactive plans are not offered on the billing page', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    SubscriptionPlan::factory()->create(['ladder' => 'individual', 'is_active' => true]);
    SubscriptionPlan::factory()->create(['ladder' => 'individual', 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('teams.billing.edit', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Billing')
            ->has('plans', 1));
});

test('a team member without update permission cannot view or change billing', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $plan = SubscriptionPlan::factory()->create(['ladder' => 'agency']);

    $this->actingAs($member)
        ->get(route('teams.billing.edit', $team))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('teams.billing.update', $team), ['subscription_plan_id' => $plan->id])
        ->assertForbidden();
});

test('a team owner can subscribe to a plan', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $plan = SubscriptionPlan::factory()->create(['ladder' => 'individual']);

    $this->actingAs($user)
        ->post(route('teams.billing.update', $team), ['subscription_plan_id' => $plan->id])
        ->assertRedirect();

    $subscription = $team->fresh()->activeSubscription();
    expect($subscription)->not->toBeNull()
        ->and($subscription->subscription_plan_id)->toBe($plan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

test('switching plans cancels the previous subscription instead of stacking rows', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $oldPlan = SubscriptionPlan::factory()->create(['ladder' => 'individual']);
    $newPlan = SubscriptionPlan::factory()->create(['ladder' => 'individual']);

    $original = TeamSubscription::factory()->for($team)->for($oldPlan, 'plan')->create();

    $this->actingAs($user)
        ->post(route('teams.billing.update', $team), ['subscription_plan_id' => $newPlan->id])
        ->assertRedirect();

    expect($original->fresh()->status)->toBe(SubscriptionStatus::Canceled);

    $live = $team->fresh()->activeSubscription();
    expect($live->subscription_plan_id)->toBe($newPlan->id);
    expect(TeamSubscription::query()->where('team_id', $team->id)->whereNot('status', SubscriptionStatus::Canceled)->count())->toBe(1);
});

test('a team cannot subscribe to a plan from a different ladder', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $agencyPlan = SubscriptionPlan::factory()->create(['ladder' => 'agency']);

    $this->actingAs($user)
        ->post(route('teams.billing.update', $team), ['subscription_plan_id' => $agencyPlan->id])
        ->assertSessionHasErrors('subscription_plan_id');

    expect($team->fresh()->activeSubscription())->toBeNull();
});

test('a team cannot subscribe to a retired plan', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $retiredPlan = SubscriptionPlan::factory()->create(['ladder' => 'individual', 'is_active' => false]);

    $this->actingAs($user)
        ->post(route('teams.billing.update', $team), ['subscription_plan_id' => $retiredPlan->id])
        ->assertSessionHasErrors('subscription_plan_id');
});
