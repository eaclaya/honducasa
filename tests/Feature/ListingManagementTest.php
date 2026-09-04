<?php

use App\Enums\ListingStatus;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use App\Enums\SubscriptionLadder;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

test('the listing form exposes the feature template for every property type', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get(route('listings.create', $user->currentTeam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('propertyTypeFields', count(PropertyType::cases()))
            ->where('propertyTypeFields.land.fields', ['lot_area_m2'])
            ->where('propertyTypeFields.land.required', ['lot_area_m2'])
            ->where('propertyTypeFields.land.supportsRentalTerms', false)
            ->where('propertyTypeFields.house.required', ['bedrooms', 'bathrooms', 'parking_spaces', 'furnishing']));
});

test('land listings discard residential characteristics and rental terms', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'type' => PropertyType::Land->value,
            'lot_area_m2' => 850,
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();

    expect($property->lot_area_m2)->toBe(850)
        ->and($property->bedrooms)->toBeNull()
        ->and($property->bathrooms)->toBeNull()
        ->and($property->parking_spaces)->toBeNull()
        ->and($property->interior_area_m2)->toBeNull()
        ->and($property->year_built)->toBeNull()
        ->and($property->furnishing)->toBeNull()
        ->and($property->deposit_amount)->toBeNull()
        ->and($property->utilities_included)->toBeNull();
});

test('land listings require their lot area', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'type' => PropertyType::Land->value,
            'lot_area_m2' => null,
        ]))
        ->assertSessionHasErrors('lot_area_m2');
});

test('guests cannot manage listings', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->get(route('listings.index', $user->currentTeam))
        ->assertRedirect(route('login'));
});

test('the listings index paginates and only ever renders one page of listings', function () {
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();
    Property::factory()->count(20)->create([
        'created_by' => $user->getKey(),
        'team_id' => null,
        'location_id' => $location->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('personal-listings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('listings/Index')
            ->has('listings.data', 18)
            ->where('listings.total', 20)
            ->where('listings.current_page', 1)
            ->where('listings.last_page', 2));

    $this->actingAs($user)
        ->get(route('personal-listings.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 2)
            ->where('listings.current_page', 2));
});

test('the listings index does not leak listings owned by another user', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();
    Property::factory()->count(3)->create([
        'created_by' => $stranger->getKey(),
        'team_id' => null,
        'location_id' => $location->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('personal-listings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 0)
            ->where('listings.total', 0));
});

test('the listings index includes location and conversation context for list actions', function () {
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create(['name' => 'Comayagua']);
    $property = Property::factory()->create([
        'created_by' => $user->id,
        'team_id' => null,
        'location_id' => $location->id,
        'name' => 'Casa del Centro',
    ]);
    Conversation::factory()->count(2)->create([
        'property_id' => $property->id,
        'team_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('personal-listings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('listings/Index')
            ->where('listings.data.0.name', 'Casa del Centro')
            ->where('listings.data.0.location', 'Comayagua')
            ->where('listings.data.0.conversationsCount', 2));
});

test('a listing owner can change its status from the listings index', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $property = Property::factory()->create([
        'team_id' => $user->currentTeam->id,
        'status' => ListingStatus::Published,
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('listings.index', $user->currentTeam))
        ->patch(route('listings.status.update', [$user->currentTeam, $property]), [
            'status' => ListingStatus::Paused->value,
        ])
        ->assertRedirect(route('listings.index', $user->currentTeam))
        ->assertSessionHas('toast.type', 'success');

    expect($property->fresh()->status)->toBe(ListingStatus::Paused)
        ->and($property->fresh()->published_at)->toBeNull();
});

test('a user cannot change another owners listing status', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $property = Property::factory()->create([
        'created_by' => $owner->id,
        'team_id' => null,
        'status' => ListingStatus::Draft,
    ]);

    $this->actingAs($outsider)
        ->patch(route('personal-listings.status.update', $property), [
            'status' => ListingStatus::Archived->value,
        ])
        ->assertForbidden();

    expect($property->fresh()->status)->toBe(ListingStatus::Draft);
});

test('publishing from the solo wizard creates an individual listing without a team', function () {
    $user = User::factory()->create(['name' => 'Ana Lopez']);
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->post(
        route('listings.start.store'),
        listingPayload($location),
    );

    $response->assertSessionHasNoErrors();

    $property = Property::query()->sole();

    expect($user->fresh()->teams()->count())->toBe(0)
        ->and($user->fresh()->current_team_id)->toBeNull()
        ->and($property->team_id)->toBeNull()
        ->and($property->created_by)->toBe($user->id)
        ->and($property->normalized_price_amount)->toBe('22000.000000')
        ->and($property->normalized_currency)->toBe('HNL')
        ->and($property->normalization_rate)->toBe('1.0000000000')
        ->and($property->price_normalized_at)->not->toBeNull();

    $response->assertRedirect(route('personal-listings.index'));
});

test('an invalid submission from the solo wizard creates neither a team nor a listing', function () {
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->post(
        route('listings.start.store'),
        listingPayload($location, ['name' => '']),
    );

    $response->assertSessionHasErrors('name');

    expect($user->fresh()->teams()->count())->toBe(0)
        ->and(Property::query()->count())->toBe(0);
});

test('an agency member can still publish an individual listing from the solo wizard', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->post(
        route('listings.start.store'),
        listingPayload($location),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('personal-listings.index'));

    expect($user->fresh()->teams()->count())->toBe(1)
        ->and(Property::query()->sole()->team_id)->toBeNull();
});

test('a team member can create and publish a listing with an image', function () {
    Storage::fake('public');
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $mediaId = (int) $this->actingAs($user)
        ->post(route('listings.uploads.store'), ['file' => compressedListingPhoto()])
        ->assertCreated()
        ->getContent();

    $response = $this->actingAs($user)->post(
        route('listings.store', $user->currentTeam),
        listingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => [$mediaId],
        ]),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $property = Property::query()->sole();

    $response->assertRedirect(route('listings.index', $user->currentTeam));
    expect($property->team_id)->toBe($user->current_team_id)
        ->and($property->status)->toBe(ListingStatus::Published)
        ->and($property->published_at)->not->toBeNull()
        ->and($property->getMedia('photos'))->toHaveCount(1);

    Storage::disk('public')->assertExists($property->getFirstMedia('photos')->getPathRelativeToRoot());
});

test('publishing beyond the team plan limit saves the listing as a draft and redirects to billing', function () {
    Storage::fake('public');
    SubscriptionPlan::factory()->entryTier()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 3,
    ]);
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update(['trial_ends_at' => now()->addDays(30)]);
    Property::factory()->count(3)->create([
        'team_id' => $team->id,
        'status' => ListingStatus::Published,
    ]);
    $location = Location::factory()->hondurasCity()->create();

    $mediaId = (int) $this->actingAs($user)
        ->post(route('listings.uploads.store'), ['file' => compressedListingPhoto()])
        ->assertCreated()
        ->getContent();

    $response = $this->actingAs($user)->post(
        route('listings.store', $team),
        listingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => [$mediaId],
        ]),
    );

    $response
        ->assertRedirect(route('teams.billing.edit', $team))
        ->assertSessionHas('toast.type', 'warning');

    $createdListing = Property::query()->where('team_id', $team->id)->latest('id')->firstOrFail();

    expect($createdListing->status)->toBe(ListingStatus::Draft)
        ->and($createdListing->published_at)->toBeNull()
        ->and($createdListing->getMedia('photos'))->toHaveCount(1);
});

test('a team at its listing limit cannot open the create wizard', function () {
    SubscriptionPlan::factory()->entryTier()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 3,
    ]);
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update(['trial_ends_at' => now()->addDays(30)]);
    Property::factory()->count(3)->create([
        'team_id' => $team->id,
        'status' => ListingStatus::Published,
    ]);

    $this->actingAs($user)
        ->get(route('listings.create', $team))
        ->assertRedirect(route('teams.billing.edit', $team))
        ->assertSessionHas('toast.type', 'warning');

    $this->actingAs($user)->get(route('listings.start'))->assertOk();
});

test('a draft can be published after the team upgrades its plan', function () {
    Storage::fake('public');
    $plan = SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Individual,
        'active_listings_limit' => 5,
    ]);
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update(['trial_ends_at' => now()->subDay()]);
    $team->subscriptions()->create([
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
    ]);
    $listing = Property::factory()->create([
        'team_id' => $team->id,
        'status' => ListingStatus::Draft,
        'published_at' => null,
    ]);
    $listing->addMedia(UploadedFile::fake()->image('home.jpg'))->toMediaCollection('photos');
    $location = Location::factory()->hondurasCity()->create();

    $response = $this->actingAs($user)->patch(
        route('listings.update', [$team, $listing]),
        listingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => $listing->getMedia('photos')->pluck('id')->all(),
        ]),
    );

    $response->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Published)
        ->and($listing->fresh()->published_at)->not->toBeNull();
});

test('a listing cannot reference another users pending photo', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $intruder = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $mediaId = (int) $this->actingAs($intruder)
        ->post(route('listings.uploads.store'), ['file' => compressedListingPhoto('secret.webp')])
        ->assertCreated()
        ->getContent();

    $this->actingAs($owner)->post(
        route('listings.store', $owner->currentTeam),
        listingPayload($location, ['images' => [$mediaId]]),
    )->assertSessionHasErrors('images');
});

test('a user can delete their own pending upload but not someone elses', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $intruder = User::factory()->withPersonalTeam()->create();

    $mediaId = (int) $this->actingAs($owner)
        ->post(route('listings.uploads.store'), ['file' => compressedListingPhoto('mine.webp')])
        ->getContent();

    $this->actingAs($intruder)->delete(route('listings.uploads.destroy', $mediaId))->assertForbidden();
    $this->actingAs($owner)->delete(route('listings.uploads.destroy', $mediaId))->assertNoContent();
});

test('the upload endpoint only stores compressed webp photos', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('listings.uploads.store'), [
            'file' => UploadedFile::fake()->image('uncompressed.jpg', 1200, 800),
        ])
        ->assertSessionHasErrors('file');

    expect($user->getMedia('pending-listing-photos'))->toHaveCount(0);

    $mediaId = (int) $this->actingAs($user)
        ->post(route('listings.uploads.store'), [
            'file' => compressedListingPhoto(),
        ])
        ->assertCreated()
        ->getContent();

    $media = $user->fresh()->getMedia('pending-listing-photos')->sole();

    expect($media->id)->toBe($mediaId)
        ->and($media->mime_type)->toBe('image/webp')
        ->and($media->size)->toBeLessThanOrEqual(2 * 1024 * 1024);
});

test('the upload endpoint returns its validation messages in spanish', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('listings.uploads.store'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
        ])
        ->assertSessionHasErrors(['file' => 'Las fotos deben estar en formato WebP.']);

    $this->actingAs($user)
        ->post(route('listings.uploads.store'), [
            'file' => UploadedFile::fake()->create('huge.webp', 20481, 'image/webp'),
        ])
        ->assertSessionHasErrors(['file' => 'La foto es demasiado grande — debe pesar como máximo 20 MB.']);
});

test('the upload endpoint accepts any resolution, compressing oversized photos under 2MB instead of rejecting them', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $noisyPath = noisyImagePath(width: 3000, height: 2000, cellSize: 6);
    expect(filesize($noisyPath))->toBeGreaterThan(2 * 1024 * 1024);

    $mediaId = (int) $this->actingAs($user)
        ->post(route('listings.uploads.store'), [
            'file' => UploadedFile::fake()->createWithContent('large.webp', file_get_contents($noisyPath)),
        ])
        ->assertCreated()
        ->getContent();

    unlink($noisyPath);

    $media = $user->fresh()->getMedia('pending-listing-photos')->sole();

    expect($media->id)->toBe($mediaId)
        ->and($media->mime_type)->toBe('image/webp')
        ->and($media->size)->toBeLessThanOrEqual(2 * 1024 * 1024);

    [$width, $height] = getimagesize($media->getPath());
    expect($width)->toBe(3000)->and($height)->toBe(2000);
});

test('saving an accepted enhancement drops the original photo it replaced', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();
    $listing = Property::factory()->create([
        'created_by' => $user->getKey(),
        'team_id' => null,
        'location_id' => $location->getKey(),
    ]);
    $original = $listing->addMedia(compressedListingPhoto('original.webp'))->toMediaCollection('photos');
    $enhanced = $user->addMedia(compressedListingPhoto('enhanced.webp'))
        ->withCustomProperties(['ai_enhanced' => true, 'source_media_id' => $original->getKey()])
        ->toMediaCollection('pending-listing-photos');

    // A client that submitted both — the exact case that rendered the same
    // room twice in the uploader.
    $this->actingAs($user)->put(
        route('personal-listings.update', $listing),
        listingPayload($location, ['images' => [$original->getKey(), $enhanced->getKey()]]),
    )->assertSessionHasNoErrors();

    $photos = $listing->fresh()->getMedia('photos');

    expect($photos)->toHaveCount(1)
        ->and($photos->first()->getCustomProperty('ai_enhanced'))->toBeTrue()
        ->and($photos->first()->getCustomProperty('source_media_id'))->toBe($original->getKey())
        ->and(Media::query()->whereKey($original->getKey())->exists())->toBeFalse()
        ->and(Media::query()->whereKey($enhanced->getKey())->exists())->toBeFalse();
});

test('an original kept without its enhancement is left alone', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $location = Location::factory()->hondurasCity()->create();
    $listing = Property::factory()->create([
        'created_by' => $user->getKey(),
        'team_id' => null,
        'location_id' => $location->getKey(),
    ]);
    $original = $listing->addMedia(compressedListingPhoto('original.webp'))->toMediaCollection('photos');
    $user->addMedia(compressedListingPhoto('enhanced.webp'))
        ->withCustomProperties(['ai_enhanced' => true, 'source_media_id' => $original->getKey()])
        ->toMediaCollection('pending-listing-photos');

    // The enhancement was generated but discarded, so nothing supersedes it.
    $this->actingAs($user)->put(
        route('personal-listings.update', $listing),
        listingPayload($location, ['images' => [$original->getKey()]]),
    )->assertSessionHasNoErrors();

    expect($listing->fresh()->getMedia('photos')->pluck('id')->all())->toBe([$original->getKey()]);
});

test('openai moderation rejects a flagged listing text field', function () {
    config()->set([
        'services.openai.api_key' => 'test-key',
        'services.openai.moderation_enabled' => true,
    ]);
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false],
                ['flagged' => true],
                ['flagged' => false],
            ],
        ]),
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location))
        ->assertSessionHasErrors('description');

    expect(Property::query()->count())->toBe(0);
    expect($user->moderationStrikes()->active()->count())->toBe(1);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/moderations'
        && $request['model'] === 'omni-moderation-latest'
        && $request['input'][1] === [
            'type' => 'text',
            'text' => 'Casa amplia y segura, cerca de comercios, escuelas y las principales vías de la ciudad.',
        ]);
});

test('spanish profanity rejects a listing title before openai safety moderation', function () {
    config()->set([
        'services.openai.api_key' => 'test-key',
        'services.openai.moderation_enabled' => true,
    ]);
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false],
                ['flagged' => false],
                ['flagged' => false],
            ],
        ]),
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'name' => 'Casa en pelame la verga',
        ]))
        ->assertSessionHasErrors('name');

    expect(Property::query()->count())->toBe(0)
        ->and($user->moderationStrikes()->active()->count())->toBe(1)
        ->and($user->moderationStrikes()->latest()->first()->metadata)->toMatchArray([
            'fields' => ['name'],
            'profanity_fields' => ['name'],
            'openai_fields' => [],
        ]);

    Http::assertSent(fn ($request): bool => $request['input'][0]['text'] === 'Casa en pelame la verga');
});

test('precognitive validation skips content moderation entirely, saving the API call', function () {
    config()->set([
        'services.openai.api_key' => 'test-key',
        'services.openai.moderation_enabled' => true,
    ]);
    Http::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->withHeaders([
            'Accept' => 'application/json',
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'name',
        ])
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'name' => 'Casa en pelame la verga',
        ]))
        ->assertNoContent();

    expect($user->moderationStrikes()->count())->toBe(0);
    Http::assertNothingSent();
});

test('openai moderation rejects a flagged photo before it is stored', function () {
    Storage::fake('public');
    config()->set([
        'services.openai.api_key' => 'test-key',
        'services.openai.moderation_enabled' => true,
    ]);
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [['flagged' => true]],
        ]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('listings.uploads.store'), ['file' => compressedListingPhoto()])
        ->assertSessionHasErrors('file');

    expect($user->getMedia('pending-listing-photos'))->toHaveCount(0);
    expect($user->moderationStrikes()->active()->count())->toBe(1);

    Http::assertSent(fn ($request): bool => $request['input'][0]['type'] === 'image_url'
        && str_starts_with($request['input'][0]['image_url']['url'], 'data:image/webp;base64,'));
});

test('listing saves fail closed when enabled moderation is unavailable', function () {
    config()->set([
        'services.openai.api_key' => 'test-key',
        'services.openai.moderation_enabled' => true,
    ]);
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([], 503),
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location))
        ->assertSessionHasErrors('name');

    expect(Property::query()->count())->toBe(0);
});

test('a draft listing is hidden from public pages and search', function () {
    $property = Property::factory()->create([
        'status' => ListingStatus::Draft,
        'published_at' => null,
    ]);

    $this->get(route('properties.show', $property))->assertNotFound();
    $this->get(route('rentals.index', ['location' => $property->location->name]))
        ->assertInertia(fn ($page) => $page->where('properties.data', []));
});

test('validation errors preserve the submitted listing fields', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();
    $payload = listingPayload($location, [
        'name' => 'Mi casa que no debe perderse',
        'bathrooms' => 99,
        'price_amount' => 18_500,
        'bedrooms' => 4,
        'utilities_included' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('listings.create', $user->currentTeam))
        ->post(route('listings.store', $user->currentTeam), $payload);

    $response->assertRedirect(route('listings.create', $user->currentTeam))
        ->assertSessionHasErrors('bathrooms');

    $this->get(route('listings.create', $user->currentTeam))
        ->assertInertia(fn ($page) => $page
            ->where('oldInput.name', $payload['name'])
            ->where('oldInput.price_amount', $payload['price_amount'])
            ->where('oldInput.bedrooms', $payload['bedrooms'])
            ->where('oldInput.utilities_included', $payload['utilities_included']));
});

test('a user cannot manage a listing owned by another team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $property = Property::factory()->create();

    $this->actingAs($user)
        ->get(route('listings.edit', [$user->currentTeam, $property]))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('listings.destroy', [$user->currentTeam, $property]))
        ->assertForbidden();
});

test('the city is derived from the map pin instead of being submitted', function () {
    $user = User::factory()->withPersonalTeam()->create();
    Location::factory()->hondurasCity()->create();
    $sanPedroSula = Location::factory()->hondurasCity('San Pedro Sula')->create();

    $payload = listingPayload($sanPedroSula);

    expect($payload)->not->toHaveKey('location_id');

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), $payload)
        ->assertSessionHasNoErrors();

    expect(Property::query()->sole()->location_id)->toBe($sanPedroSula->id);
});

test('a pin that matches no supported city is rejected', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $unsavedCity = Location::factory()->hondurasCity()->make();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($unsavedCity))
        ->assertSessionHasErrors('location_id');
});

test('a listing does not need a description', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'description' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect(Property::query()->sole()->description)->toBeNull();
});

test('publishing a listing without photos saves it as a draft instead', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'status' => ListingStatus::Published->value,
            'images' => [],
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();

    expect($property->status)->toBe(ListingStatus::Draft)
        ->and($property->published_at)->toBeNull();
});

test('an approximate location keeps the pin the publisher chose', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity('San Pedro Sula')->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'radius',
            'approximate_radius_km' => 0.3,
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();
    $point = DB::table('properties')
        ->where('id', $property->id)
        ->selectRaw('ST_Y(coordinates::geometry) latitude, ST_X(coordinates::geometry) longitude')
        ->first();

    expect($property->public_location_precision)->toBe(LocationPrecision::Approximate)
        ->and($property->approximate_shape->value)->toBe('radius')
        ->and($property->approximate_radius_meters)->toBe(300)
        ->and((float) $point->latitude)->toBe(15.5057)
        ->and((float) $point->longitude)->toBe(-88.025);
});

test('a custom approximate polygon is validated and persisted', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();
    $polygon = [
        'type' => 'Polygon',
        'coordinates' => [[
            [-87.22, 14.05],
            [-87.16, 14.05],
            [-87.18, 14.10],
            [-87.22, 14.05],
        ]],
    ];

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'polygon',
            'approximate_polygon' => json_encode($polygon, JSON_THROW_ON_ERROR),
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::query()->sole();

    expect($property->approximate_shape->value)->toBe('polygon')
        ->and($property->approximate_radius_meters)->toBeNull()
        ->and($property->approximate_polygon)->toBe($polygon);
});

test('a custom approximate polygon must be closed and stay inside Honduras', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'polygon',
            'approximate_polygon' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[
                    [-87.22, 14.05],
                    [-87.16, 14.05],
                    [-82.00, 14.10],
                    [-87.18, 14.10],
                ]],
            ], JSON_THROW_ON_ERROR),
        ]))
        ->assertSessionHasErrors([
            'approximate_polygon',
            'approximate_polygon.coordinates.0.2.0',
        ]);
});

test('an exact location requires a map pin inside Honduras', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Exact->value,
            'latitude' => null,
            'longitude' => null,
        ]))
        ->assertSessionHasErrors(['latitude', 'longitude']);
});

test('an approximate radius only accepts 100 meter steps from 100 meters to 500 meters', function (float $radius) {
    $user = User::factory()->withPersonalTeam()->create();
    $location = Location::factory()->hondurasCity()->create();

    $this->actingAs($user)
        ->post(route('listings.store', $user->currentTeam), listingPayload($location, [
            'location_mode' => LocationPrecision::Approximate->value,
            'approximate_shape' => 'radius',
            'approximate_radius_km' => $radius,
        ]))
        ->assertSessionHasErrors('approximate_radius_km');
})->with([0.05, 0.15, 0.6, 25]);
