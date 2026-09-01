<?php

use App\Enums\ListingType;
use App\Models\Location;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SavedSearchMatchesFound;
use Illuminate\Support\Facades\Notification;

test('the command sends a private notification when a new listing matches', function () {
    Notification::fake();
    $user = User::factory()->create();
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'filters' => ['location' => 'Tegucigalpa', 'listing_type' => 'rent'],
        'created_at' => now()->subHour(),
    ]);
    Property::factory()->create(['location_id' => $location->id, 'listing_type' => ListingType::Rent, 'published_at' => now()]);

    $this->artisan('app:send-saved-search-alerts')->assertSuccessful();

    Notification::assertSentTo($user, SavedSearchMatchesFound::class, fn ($notification) => $notification->matchCount === 1);
    expect($search->fresh()->last_notified_at)->not->toBeNull();
});

test('saved search alerts compare price filters across currencies', function () {
    Notification::fake();
    $user = User::factory()->create();
    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'filters' => ['currency' => 'HNL', 'min_price' => 24_000],
        'created_at' => now()->subHour(),
    ]);
    Property::factory()->create([
        'currency' => 'USD',
        'price_amount' => 1_000,
        'published_at' => now(),
    ]);

    $this->artisan('app:send-saved-search-alerts')->assertSuccessful();

    Notification::assertSentTo(
        $user,
        SavedSearchMatchesFound::class,
        fn ($notification) => $notification->matchCount === 1,
    );
});

test('the command does not notify for old or nonmatching listings', function () {
    Notification::fake();
    $user = User::factory()->create();
    $location = Location::factory()->create(['name' => 'San Pedro Sula']);
    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'filters' => ['location' => 'Tegucigalpa', 'listing_type' => 'rent'],
        'created_at' => now()->subHour(),
    ]);
    Property::factory()->create(['location_id' => $location->id, 'published_at' => now()]);

    $this->artisan('app:send-saved-search-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the command does not notify about listings from a suspended team', function () {
    Notification::fake();
    $user = User::factory()->create();
    $location = Location::factory()->create(['name' => 'Tegucigalpa']);
    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'filters' => ['location' => 'Tegucigalpa', 'listing_type' => 'rent'],
        'created_at' => now()->subHour(),
    ]);
    $suspendedTeam = Team::factory()->create(['suspended_at' => now()]);
    Property::factory()->create([
        'team_id' => $suspendedTeam->id,
        'location_id' => $location->id,
        'listing_type' => ListingType::Rent,
        'published_at' => now(),
    ]);

    $this->artisan('app:send-saved-search-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});

test('disabled saved searches are skipped', function () {
    Notification::fake();
    $user = User::factory()->create();
    SavedSearch::factory()->create(['user_id' => $user->id, 'alerts_enabled' => false]);
    Property::factory()->create(['published_at' => now()]);

    $this->artisan('app:send-saved-search-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});
