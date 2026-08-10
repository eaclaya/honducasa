<?php

use App\Enums\ListingStatus;
use App\Models\AdminActivity;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the properties index lists inventory across every team with status facets', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Property::factory()->create(['status' => ListingStatus::Published]);
    Property::factory()->create(['status' => ListingStatus::Draft]);

    $this->actingAs($admin)
        ->get(route('admin.properties.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 2)
            ->where('facetCounts.all', 2)
            ->where('facetCounts.published', 1)
            ->where('facetCounts.draft', 1));

    $this->actingAs($admin)
        ->get(route('admin.properties.index', ['status' => 'published']))
        ->assertInertia(fn (Assert $page) => $page->has('properties.data', 1));
});

test('an admin archiving a property is recorded on the audit trail', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $property = Property::factory()->create(['status' => ListingStatus::Published]);

    $this->actingAs($admin)
        ->patch(route('admin.properties.status.update', $property), [
            'status' => 'archived',
            'reason' => 'Duplicate listing',
        ])
        ->assertRedirect();

    expect($property->fresh()->status)->toBe(ListingStatus::Archived);

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('property.status_changed')
        ->and($activity->subject->is($property))->toBeTrue()
        ->and($activity->changes['status']['from'])->toBe('published')
        ->and($activity->changes['status']['to'])->toBe('archived');
});

test('an admin cannot publish a property without photos, even through the console', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $property = Property::factory()->create(['status' => ListingStatus::Draft, 'published_at' => null]);

    $this->actingAs($admin)
        ->patch(route('admin.properties.status.update', $property), ['status' => 'published'])
        ->assertRedirect();

    expect($property->fresh()->status)->toBe(ListingStatus::Draft);
});

test('an admin can publish a property that has photos', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $property = Property::factory()->create(['status' => ListingStatus::Draft, 'published_at' => null]);
    $property->addMedia(UploadedFile::fake()->image('casa.jpg'))->toMediaCollection('photos');

    $this->actingAs($admin)
        ->patch(route('admin.properties.status.update', $property->fresh()), ['status' => 'published'])
        ->assertRedirect();

    expect($property->fresh()->status)->toBe(ListingStatus::Published);
});
