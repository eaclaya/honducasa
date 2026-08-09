<?php

use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\User;

test('verified users can add and remove a published property favorite', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $property = Property::factory()->create();

    $this->actingAs($user)->post(route('favorites.store', $property))->assertRedirect();
    $this->assertDatabaseHas('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);

    $this->actingAs($user)->delete(route('favorites.destroy', $property))->assertRedirect();
    $this->assertDatabaseMissing('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);
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
    ])->assertRedirect();

    $search = SavedSearch::query()->sole();
    $this->actingAs($user)->patch(route('saved-searches.update', $search), ['alerts_enabled' => false])->assertRedirect();
    expect($search->fresh()->alerts_enabled)->toBeFalse();
    $this->actingAs($user)->delete(route('saved-searches.destroy', $search))->assertRedirect();
    $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
});

test('users cannot change another users saved search', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $search = SavedSearch::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->patch(route('saved-searches.update', $search), ['alerts_enabled' => false])->assertForbidden();
    $this->actingAs($other)->delete(route('saved-searches.destroy', $search))->assertForbidden();
});
