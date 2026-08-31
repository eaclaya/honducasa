<?php

use App\Jobs\EnhanceListingPhoto;
use App\Models\Property;
use App\Models\User;
use App\Services\OpenAiPropertyPhotoEnhancer;
use App\Support\PhotoEnhancementStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    $job->handle(app(OpenAiPropertyPhotoEnhancer::class), app(PhotoEnhancementStatus::class));

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
