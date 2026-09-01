<?php

use App\Jobs\EnhanceListingPhoto;
use App\Models\ListingPhotoEnhancement;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use App\Services\ListingPhotoCompressor;
use App\Services\OpenAiPropertyPhotoEnhancer;
use App\Support\ListingPhotoEnhancementQuota;
use App\Support\PhotoEnhancementStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config()->set('services.openai.api_key', 'test-key');
});

test('a user can create an enhanced candidate for their pending listing photo', function () {
    Queue::fake();
    $user = User::factory()->create();
    $source = $user->addMedia(compressedListingPhoto())
        ->usingFileName('blob')
        ->toMediaCollection('pending-listing-photos');
    $response = $this->actingAs($user)->postJson(route('listings.uploads.enhance', $source));

    $response->assertAccepted()->assertJsonStructure(['request_id']);
    Queue::assertPushed(EnhanceListingPhoto::class, fn (EnhanceListingPhoto $job): bool => $job->mediaId === $source->getKey()
        && $job->userId === $user->getKey());
    Http::assertNothingSent();
});

test('a user cannot enhance another users pending photo', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $source = $owner->addMedia(compressedListingPhoto())->toMediaCollection('pending-listing-photos');

    Http::fake();

    $this->actingAs($intruder)
        ->postJson(route('listings.uploads.enhance', $source))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('a listing owner can enhance a photo already saved on their property', function () {
    Queue::fake();
    $user = User::factory()->create();
    $property = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    $source = $property->addMedia(compressedListingPhoto())
        ->usingFileName('blob')
        ->toMediaCollection('photos');
    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $source))
        ->assertAccepted();

    Queue::assertPushed(EnhanceListingPhoto::class);
});

test('the queued job creates a candidate and publishes completed status', function () {
    $user = User::factory()->create();
    $source = $user->addMedia(compressedListingPhoto())
        ->usingFileName('blob')
        ->toMediaCollection('pending-listing-photos');
    $enhancedBytes = file_get_contents($source->getPath());
    $requestId = (string) Str::uuid();

    Http::fake([
        '*/images/edits' => Http::response([
            'data' => [['b64_json' => base64_encode($enhancedBytes)]],
        ]),
    ]);

    $job = new EnhanceListingPhoto($source->getKey(), $user->getKey(), $requestId);
    $job->handle(app(OpenAiPropertyPhotoEnhancer::class), app(PhotoEnhancementStatus::class), app(ListingPhotoCompressor::class));

    $candidate = $user->fresh()->getMedia('pending-listing-photos')->last();
    $status = app(PhotoEnhancementStatus::class)->get($user->getKey(), $requestId);

    expect($candidate->getKey())->not->toBe($source->getKey())
        ->and($candidate->getCustomProperty('ai_enhanced'))->toBeTrue()
        ->and($status['status'])->toBe('completed')
        ->and($status['candidate']['id'])->toBe($candidate->getKey());

    Http::assertSent(fn ($request): bool => str_contains($request->body(), 'filename="blob.webp"')
        && str_contains($request->body(), 'Content-Type: image/webp'));
});

test('enhancement status is isolated to the requesting user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $requestId = (string) Str::uuid();

    app(PhotoEnhancementStatus::class)->put($owner->getKey(), $requestId, ['status' => 'processing']);

    $this->actingAs($owner)
        ->getJson(route('listings.uploads.enhancement-status', $requestId))
        ->assertOk()
        ->assertJson(['status' => 'processing']);

    $this->actingAs($intruder)
        ->getJson(route('listings.uploads.enhancement-status', $requestId))
        ->assertNotFound();
});

test('each enhancement is booked against the listing, and the sixth is refused', function () {
    Queue::fake();
    $user = User::factory()->create();
    $property = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    $source = $property->addMedia(compressedListingPhoto())
        ->usingFileName('blob')
        ->toMediaCollection('photos');

    foreach (range(1, ListingPhotoEnhancement::PER_LISTING_LIMIT) as $attempt) {
        $this->actingAs($user)
            ->postJson(route('listings.uploads.enhance', $source))
            ->assertAccepted()
            ->assertJson(['remaining' => ListingPhotoEnhancement::PER_LISTING_LIMIT - $attempt]);
    }

    // The route's own `throttle:5,1` would answer the sixth call first; step
    // past its window so it's the per-listing allowance being asserted here.
    $this->travel(2)->minutes();

    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $source))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media');

    expect(ListingPhotoEnhancement::query()->forListing($property)->count())
        ->toBe(ListingPhotoEnhancement::PER_LISTING_LIMIT);
    Queue::assertPushed(EnhanceListingPhoto::class, ListingPhotoEnhancement::PER_LISTING_LIMIT);
});

test('a discarded enhancement still consumes the allowance', function () {
    Queue::fake();
    $user = User::factory()->create();
    $property = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    $source = $property->addMedia(compressedListingPhoto())->toMediaCollection('photos');

    $candidateId = (int) $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $source))
        ->assertAccepted()
        ->json('remaining');

    // Throwing the candidate away does not refund the OpenAI call it cost.
    expect($candidateId)->toBe(ListingPhotoEnhancement::PER_LISTING_LIMIT - 1)
        ->and(ListingPhotoEnhancement::query()->forListing($property)->count())->toBe(1);
});

test('one listing exhausting its allowance leaves another listing untouched', function () {
    Queue::fake();
    $user = User::factory()->create();
    $spent = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    $fresh = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    ListingPhotoEnhancement::factory()
        ->count(ListingPhotoEnhancement::PER_LISTING_LIMIT)
        ->forListing($spent)
        ->create(['user_id' => $user->getKey()]);

    $spentPhoto = $spent->addMedia(compressedListingPhoto())->toMediaCollection('photos');
    $freshPhoto = $fresh->addMedia(compressedListingPhoto())->toMediaCollection('photos');

    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $spentPhoto))
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $freshPhoto))
        ->assertAccepted()
        ->assertJson(['remaining' => ListingPhotoEnhancement::PER_LISTING_LIMIT - 1]);
});

test('an edit-mode enhancement of a pending upload is charged to the listing it names', function () {
    Queue::fake();
    $user = User::factory()->create();
    $property = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    ListingPhotoEnhancement::factory()
        ->count(ListingPhotoEnhancement::PER_LISTING_LIMIT)
        ->forListing($property)
        ->create(['user_id' => $user->getKey()]);

    $pending = $user->addMedia(compressedListingPhoto())->toMediaCollection('pending-listing-photos');

    // Without the `listing` field this would fall through to the untouched
    // draft allowance and hand out five more enhancements per edit.
    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $pending), ['listing' => $property->getKey()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('media');

    Queue::assertNothingPushed();
});

test('naming a listing the user cannot edit is refused rather than ignored', function () {
    Queue::fake();
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $strangerListing = Property::factory()->create(['created_by' => $stranger->id, 'team_id' => null]);
    $pending = $user->addMedia(compressedListingPhoto())->toMediaCollection('pending-listing-photos');

    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $pending), ['listing' => $strangerListing->getKey()])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('wizard usage carries onto the listing it becomes, instead of resetting on save', function () {
    Queue::fake();
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();
    $pending = $user->addMedia(compressedListingPhoto())->toMediaCollection('pending-listing-photos');

    // Two enhancements in the wizard, before any listing exists.
    $this->actingAs($user)->postJson(route('listings.uploads.enhance', $pending))->assertAccepted();
    $this->actingAs($user)
        ->postJson(route('listings.uploads.enhance', $pending))
        ->assertAccepted()
        ->assertJson(['remaining' => ListingPhotoEnhancement::PER_LISTING_LIMIT - 2]);

    expect(ListingPhotoEnhancement::query()->whereNull('property_id')->count())->toBe(2);

    $this->actingAs($user)
        ->post(route('listings.start.store'), listingPayload($location) + ['images' => [$pending->getKey()]])
        ->assertSessionHasNoErrors();

    $listing = Property::query()->sole();

    expect(ListingPhotoEnhancement::query()->forListing($listing)->count())->toBe(2)
        ->and(ListingPhotoEnhancement::query()->whereNull('property_id')->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('personal-listings.edit', $listing))
        ->assertInertia(fn (Assert $page) => $page
            ->where('photoEnhancementsRemaining', ListingPhotoEnhancement::PER_LISTING_LIMIT - 2));
});

test('the create wizard reports the draft allowance it has left', function () {
    Queue::fake();
    $user = User::factory()->create();
    $pending = $user->addMedia(compressedListingPhoto())->toMediaCollection('pending-listing-photos');

    $this->actingAs($user)->postJson(route('listings.uploads.enhance', $pending))->assertAccepted();

    $this->actingAs($user)
        ->get(route('listings.start'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('photoEnhancementsRemaining', ListingPhotoEnhancement::PER_LISTING_LIMIT - 1));
});

test('the quota claims only the draft that belongs to the saving user', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $listing = Property::factory()->create(['created_by' => $user->id, 'team_id' => null]);
    $quota = app(ListingPhotoEnhancementQuota::class);

    ListingPhotoEnhancement::factory()->create(['user_id' => $stranger->getKey(), 'draft_key' => 'shared-key']);
    ListingPhotoEnhancement::factory()->create(['user_id' => $user->getKey(), 'draft_key' => 'shared-key']);

    $request = request();
    $request->setLaravelSession(app('session.store'));
    $request->session()->put(ListingPhotoEnhancementQuota::DRAFT_SESSION_KEY, 'shared-key');

    $quota->claimDraft($request, $user, $listing);

    expect(ListingPhotoEnhancement::query()->forListing($listing)->count())->toBe(1)
        ->and(ListingPhotoEnhancement::query()->where('user_id', $stranger->getKey())->whereNull('property_id')->count())->toBe(1);
});
