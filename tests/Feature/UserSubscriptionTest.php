<?php

use App\Enums\ListingStatus;
use App\Enums\SubscriptionLadder;
use App\Models\Location;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\PlanSubscriptionUpdated;
use App\Support\HondurasCityCoordinates;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an individual can subscribe without creating a team', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Individual,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('billing.update'), ['subscription_plan_id' => $plan->id])
        ->assertRedirect();

    expect(UserSubscription::query()->sole()->user_id)->toBe($user->id)
        ->and($user->fresh()->teams()->count())->toBe(0);
});

test('subscribing to a plan emails the individual a confirmation', function () {
    Notification::fake();
    $user = User::factory()->create();
    $plan = SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Individual,
        'is_active' => true,
        'name' => 'Individual — Plus',
    ]);

    $this->actingAs($user)
        ->post(route('billing.update'), ['subscription_plan_id' => $plan->id])
        ->assertRedirect();

    Notification::assertSentTo($user, PlanSubscriptionUpdated::class, function ($notification, $channels) {
        return $notification->planName === 'Individual — Plus'
            && in_array('mail', $channels, true);
    });
});

test('the personal billing page only lists individual plans', function () {
    $user = User::factory()->create();
    $individual = SubscriptionPlan::factory()->create(['ladder' => SubscriptionLadder::Individual]);
    SubscriptionPlan::factory()->create(['ladder' => SubscriptionLadder::Agency]);

    $this->actingAs($user)
        ->get(route('billing.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Billing')
            ->has('plans', 1)
            ->where('plans.0.id', $individual->id));
});

test('an individual can publish a property without a team during the trial', function () {
    Storage::fake('public');
    SubscriptionPlan::factory()->entryTier()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 3,
    ]);
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();
    $mediaId = (int) $this->actingAs($user)
        ->post(route('listings.uploads.store'), ['file' => compressedListingPhoto()])
        ->assertCreated()
        ->getContent();

    $this->actingAs($user)
        ->post(route('personal-listings.store'), individualListingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => [$mediaId],
        ]))
        ->assertRedirect(route('personal-listings.index'));

    $property = Property::query()->sole();

    expect($property->team_id)->toBeNull()
        ->and($property->created_by)->toBe($user->id)
        ->and($property->status)->toBe(ListingStatus::Published)
        ->and($user->fresh()->teams()->count())->toBe(0);
});

/** @param array<string, mixed> $overrides */
function individualListingPayload(Location $location, array $overrides = []): array
{
    $center = HondurasCityCoordinates::for($location->name);

    return array_replace([
        'name' => 'Casa individual',
        'type' => 'house',
        'listing_type' => 'rent',
        'status' => 'draft',
        'price_amount' => 22_000,
        'currency' => 'HNL',
        'deposit_amount' => 22_000,
        'utilities_included' => false,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 2,
        'interior_area_m2' => 180,
        'lot_area_m2' => 300,
        'year_built' => 2022,
        'furnishing' => 'unfurnished',
        'description' => 'Casa amplia y segura, cerca de comercios, escuelas y las principales vías de la ciudad.',
        'address_line' => 'Colonia Palmira, Tegucigalpa',
        'location_mode' => 'exact',
        'latitude' => $center?->latitude,
        'longitude' => $center?->longitude,
        'approximate_shape' => null,
        'approximate_radius_km' => null,
        'approximate_polygon' => null,
    ], $overrides);
}
