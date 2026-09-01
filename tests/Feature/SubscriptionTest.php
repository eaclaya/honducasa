<?php

use App\Actions\Listings\SetListingStatus;
use App\Enums\ListingStatus;
use App\Enums\SubscriptionLadder;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('a team that predates the subscription system is grandfathered as unrestricted', function () {
    $team = Team::factory()->create(['trial_ends_at' => null]);

    expect($team->isOnTrial())->toBeFalse()
        ->and($team->currentPlan())->toBeNull()
        ->and($team->canPublishAnotherListing())->toBeTrue();
});

test('a team on trial gets its ladder entry-tier plan and limit', function () {
    $entryTier = SubscriptionPlan::factory()->entryTier()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 2,
    ]);
    SubscriptionPlan::factory()->create(['ladder' => SubscriptionLadder::Agency, 'is_entry_tier' => true]);
    $team = Team::factory()->personal()->create(['trial_ends_at' => now()->addDays(30)]);

    expect($team->isOnTrial())->toBeTrue()
        ->and($team->currentPlan()?->is($entryTier))->toBeTrue()
        ->and($team->canPublishAnotherListing())->toBeTrue();

    Property::factory()->count(2)->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);

    expect($team->canPublishAnotherListing())->toBeFalse();
});

test('a team whose trial expired without subscribing cannot publish', function () {
    SubscriptionPlan::factory()->entryTier()->create(['ladder' => SubscriptionLadder::Individual]);
    $team = Team::factory()->personal()->create(['trial_ends_at' => now()->subDay()]);

    expect($team->isOnTrial())->toBeFalse()
        ->and($team->currentPlan())->toBeNull()
        ->and($team->canPublishAnotherListing())->toBeFalse();
});

test('a subscribed team uses its own plan limit, not the trial entry tier', function () {
    $plan = SubscriptionPlan::factory()->create(['active_listings_limit' => 5]);
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(30)]);
    $team->subscriptions()->create([
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    expect($team->isOnTrial())->toBeFalse()
        ->and($team->currentPlan()?->is($plan))->toBeTrue();
});

test('subscribing early ends the trial even while trial_ends_at is still in the future', function () {
    $plan = SubscriptionPlan::factory()->create();
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(20)]);

    expect($team->isOnTrial())->toBeTrue();

    $team->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active']);

    expect($team->isOnTrial())->toBeFalse();
});

test('publishing beyond the plan limit downgrades the listing to draft', function () {
    Storage::fake('public');
    SubscriptionPlan::factory()->entryTier()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 1,
    ]);
    $team = Team::factory()->personal()->create(['trial_ends_at' => now()->addDays(30)]);
    Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);
    $overLimit = Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Draft, 'published_at' => null]);
    $overLimit->addMedia(UploadedFile::fake()->image('casa.jpg'))->toMediaCollection('photos');

    $status = app(SetListingStatus::class)->handle($overLimit->fresh(), ListingStatus::Published);

    expect($status)->toBe(ListingStatus::Draft)
        ->and($overLimit->fresh()->status)->toBe(ListingStatus::Draft);
});

test('re-publishing an already-published listing does not need additional room', function () {
    Storage::fake('public');
    SubscriptionPlan::factory()->entryTier()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 1,
    ]);
    $team = Team::factory()->personal()->create(['trial_ends_at' => now()->addDays(30)]);
    $property = Property::factory()->create(['team_id' => $team->id, 'status' => ListingStatus::Published]);
    $property->addMedia(UploadedFile::fake()->image('casa.jpg'))->toMediaCollection('photos');

    $status = app(SetListingStatus::class)->handle($property->fresh(), ListingStatus::Published);

    expect($status)->toBe(ListingStatus::Published);
});

test('allowedFor downgrades a publish request when outside the plan limit', function () {
    expect(SetListingStatus::allowedFor(ListingStatus::Published, photoCount: 1, withinPlanLimit: false))
        ->toBe(ListingStatus::Draft)
        ->and(SetListingStatus::allowedFor(ListingStatus::Published, photoCount: 1, withinPlanLimit: true))
        ->toBe(ListingStatus::Published)
        ->and(SetListingStatus::allowedFor(ListingStatus::Archived, photoCount: 0, withinPlanLimit: false))
        ->toBe(ListingStatus::Archived);
});
