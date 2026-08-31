<?php

use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\Team;
use App\Models\User;

test('verified users can add and remove a published property favorite', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $property = Property::factory()->create();

    $this->actingAs($user)->post(route('favorites.store', $property))->assertRedirect();
    $this->assertDatabaseHas('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);

    $this->actingAs($user)->delete(route('favorites.destroy', $property))->assertRedirect();
    $this->assertDatabaseMissing('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);
});

test('a property from a suspended team cannot be favorited', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $suspendedTeam = Team::factory()->create(['suspended_at' => now()]);
    $property = Property::factory()->create(['team_id' => $suspendedTeam->id]);

    $this->actingAs($user)->post(route('favorites.store', $property))->assertNotFound();
    $this->assertDatabaseMissing('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);
});

test('favorites from a since-suspended team are hidden from the favorites list', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $suspendedTeam = Team::factory()->create();
    $property = Property::factory()->create(['team_id' => $suspendedTeam->id]);
    $user->propertyFavorites()->create(['property_id' => $property->id]);

    $suspendedTeam->update(['suspended_at' => now()]);

    $this->actingAs($user)->get(route('favorites.index'))
        ->assertInertia(fn ($page) => $page->component('favorites/Index')->has('favorites.data', 0));
});

test('favorites are private to their owner', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $property = Property::factory()->create();
    $owner->propertyFavorites()->create(['property_id' => $property->id]);

    $this->actingAs($other)->get(route('favorites.index'))
        ->assertInertia(fn ($page) => $page->component('favorites/Index')->has('favorites.data', 0));
});

test('users can save update and delete their searches', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post(route('saved-searches.store'), [
        'name' => 'Apartamentos en Tegucigalpa',
        'filters' => ['location' => 'Tegucigalpa', 'listing_type' => 'rent'],
        'alerts_enabled' => true,
    ])->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Búsqueda guardada.');

    $search = SavedSearch::query()->sole();
    $this->actingAs($user)->patch(route('saved-searches.update', $search), ['alerts_enabled' => false])->assertRedirect();
    expect($search->fresh()->alerts_enabled)->toBeFalse();
    $this->actingAs($user)->delete(route('saved-searches.destroy', $search))->assertRedirect();
    $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
});

test('the same logical search can only be saved once per user', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post(route('saved-searches.store'), [
        'name' => 'Casas en renta',
        'filters' => [
            'location' => 'Tegucigalpa',
            'listing_type' => 'rent',
            'sort' => 'newest',
        ],
    ])->assertRedirect();

    $this->actingAs($user)->post(route('saved-searches.store'), [
        'name' => 'Duplicate name',
        'filters' => [
            'listing_type' => 'rent',
            'location' => 'Tegucigalpa',
        ],
    ])->assertRedirect()
        ->assertInertiaFlash('toast.type', 'info')
        ->assertInertiaFlash('toast.message', 'Esta búsqueda ya está guardada.');

    expect($user->savedSearches()->count())->toBe(1);
});

test('users can update a saved search with refined filters', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $savedSearch = SavedSearch::factory()->for($user)->create();
    $originalFingerprint = $savedSearch->fingerprint;

    $this->actingAs($user)->patch(route('saved-searches.update', $savedSearch), [
        'filters' => [
            'location' => 'Tegucigalpa',
            'listing_type' => 'rent',
            'bedrooms' => 3,
            'property_type' => 'house',
        ],
    ])->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Búsqueda actualizada.');

    $savedSearch->refresh();

    expect($savedSearch->filters)->toMatchArray([
        'location' => 'Tegucigalpa',
        'listing_type' => 'rent',
        'bedrooms' => 3,
        'property_type' => 'house',
    ])->and($savedSearch->fingerprint)->not->toBe($originalFingerprint);
});

test('updating filters cannot duplicate another saved search', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $original = SavedSearch::factory()->for($user)->create();
    $other = SavedSearch::factory()->for($user)->create([
        'filters' => ['location' => 'San Pedro Sula', 'listing_type' => 'rent'],
    ]);

    $this->actingAs($user)->patch(route('saved-searches.update', $original), [
        'filters' => $other->filters,
    ])->assertRedirect()
        ->assertInertiaFlash('toast.type', 'info');

    expect($original->fresh()->filters)->toBe($original->filters)
        ->and($user->savedSearches()->count())->toBe(2);
});

test('saved searches are shown on the home page for their owner', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $savedSearch = SavedSearch::factory()->for($user)->create();

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('savedSearches', 1)
            ->where('savedSearches.0.id', $savedSearch->id)
            ->where('savedSearches.0.filters.location', 'Tegucigalpa'));

    auth()->logout();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->has('savedSearches', 0));
});

test('users cannot change another users saved search', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $search = SavedSearch::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->patch(route('saved-searches.update', $search), ['alerts_enabled' => false])->assertForbidden();
    $this->actingAs($other)->delete(route('saved-searches.destroy', $search))->assertForbidden();
});
