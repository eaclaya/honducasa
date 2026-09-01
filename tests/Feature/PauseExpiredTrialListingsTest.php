<?php

use App\Enums\ListingStatus;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it pauses published listings for teams whose trial ended without a subscription', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $property = Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);

    $this->artisan('app:pause-expired-trial-listings')->assertSuccessful();

    expect($property->fresh()->status)->toBe(ListingStatus::Paused);
});

test('it leaves teams still on trial alone', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(10)]);
    $property = Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);

    $this->artisan('app:pause-expired-trial-listings')->assertSuccessful();

    expect($property->fresh()->status)->toBe(ListingStatus::Published);
});

test('it leaves subscribed teams alone even after their trial window passed', function () {
    $plan = SubscriptionPlan::factory()->create();
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $team->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active']);
    $property = Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);

    $this->artisan('app:pause-expired-trial-listings')->assertSuccessful();

    expect($property->fresh()->status)->toBe(ListingStatus::Published);
});

test('it leaves teams that predate the subscription system alone', function () {
    $team = Team::factory()->create(['trial_ends_at' => null]);
    $property = Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);

    $this->artisan('app:pause-expired-trial-listings')->assertSuccessful();

    expect($property->fresh()->status)->toBe(ListingStatus::Published);
});
