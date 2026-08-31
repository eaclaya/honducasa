<?php

use App\Models\AdminActivity;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the teams index hides soft-deleted teams unless requested', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $active = Team::factory()->create();
    $deleted = Team::factory()->trashed()->create();

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teams.data', 1)
            ->where('teams.data.0.id', $active->id));

    $this->actingAs($admin)
        ->get(route('admin.teams.index', ['show_deleted' => true]))
        ->assertInertia(fn (Assert $page) => $page->has('teams.data', 2));

    expect($deleted->trashed())->toBeTrue();
});

test('an admin can suspend and reinstate a team with a reason recorded', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.teams.suspension.update', $team), [
            'suspended' => true,
            'reason' => 'Repeated listing violations',
        ])
        ->assertRedirect();

    expect($team->fresh()->isSuspended())->toBeTrue();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.suspended')
        ->and($activity->subject->is($team))->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.teams.suspension.update', $team), ['suspended' => false])
        ->assertRedirect();

    expect($team->fresh()->isSuspended())->toBeFalse();
});

test('an admin can restore a soft-deleted team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->trashed()->create();

    $this->actingAs($admin)
        ->patch(route('admin.teams.restore', $team))
        ->assertRedirect();

    expect($team->fresh()->trashed())->toBeFalse();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.restored');
});

test('the teams index reports property counts per team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    Property::factory()->count(2)->create(['team_id' => $team->id, 'status' => 'published']);

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teams.data.0.propertiesCount', 2)
            ->where('teams.data.0.publishedPropertiesCount', 2));
});

test('an admin can extend a team\'s trial, which is recorded on the audit trail', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(5)]);

    $this->actingAs($admin)
        ->patch(route('admin.teams.trial.update', $team), ['days' => 14, 'reason' => 'Goodwill extension'])
        ->assertRedirect();

    expect($team->fresh()->trial_ends_at->isBetween(now()->addDays(18), now()->addDays(20)))->toBeTrue();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.trial_extended');
});

test('extending a trial never shortens it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(25)]);

    $this->actingAs($admin)
        ->patch(route('admin.teams.trial.update', $team), ['days' => 1]);

    expect($team->fresh()->trial_ends_at->isAfter(now()->addDays(25)))->toBeTrue();
});

test('an admin can comp a team onto a plan for free', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.teams.subscription.comp', $team), [
            'subscription_plan_id' => $plan->id,
            'reason' => 'VIP landlord',
        ])
        ->assertRedirect();

    $subscription = $team->fresh()->activeSubscription();
    expect($subscription)->not->toBeNull()
        ->and($subscription->subscription_plan_id)->toBe($plan->id)
        ->and($subscription->provider_customer_id)->toBeNull();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.subscription_comped');
});

test('a team cannot be comped twice while already subscribed', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create();
    $team = Team::factory()->create();
    $team->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.teams.subscription.comp', $team), ['subscription_plan_id' => $plan->id])
        ->assertStatus(422);

    expect($team->subscriptions()->count())->toBe(1);
});

test('an admin can cancel a team\'s subscription', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create();
    $team = Team::factory()->create();
    $team->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active']);

    $this->actingAs($admin)
        ->delete(route('admin.teams.subscription.cancel', $team), ['reason' => 'Customer requested via support'])
        ->assertRedirect();

    expect($team->fresh()->activeSubscription())->toBeNull();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.subscription_canceled');
});
