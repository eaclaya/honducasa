<?php

use App\Exceptions\ContentModerationUnavailableException;
use App\Services\OpenAiContentModerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set([
        'services.openai.api_key' => 'test-key',
        'services.openai.base_url' => 'https://api.openai.com/v1',
        'services.openai.moderation_enabled' => true,
        'services.openai.moderation_model' => 'omni-moderation-latest',
    ]);
});

test('it identifies each flagged text field', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false],
                ['flagged' => true],
                ['flagged' => false],
            ],
        ]),
    ]);

    $fields = app(OpenAiContentModerator::class)->flaggedTextFields([
        'name' => 'Casa familiar',
        'description' => 'Flagged content',
        'address_line' => 'Colonia Palmira',
    ]);

    expect($fields)->toBe(['description']);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/moderations'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'omni-moderation-latest'
        && $request['input'][0] === [
            'type' => 'text',
            'text' => 'Casa familiar',
        ]);
});

test('it sends uploaded images as data urls', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [['flagged' => true]],
        ]),
    ]);

    $flagged = app(OpenAiContentModerator::class)->imageIsFlagged(compressedListingPhoto());

    expect($flagged)->toBeTrue();

    Http::assertSent(fn ($request): bool => $request['input'][0]['type'] === 'image_url'
        && str_starts_with($request['input'][0]['image_url']['url'], 'data:image/webp;base64,'));
});

test('it fails closed when openai is unavailable', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([], 503),
    ]);

    app(OpenAiContentModerator::class)->flaggedTextFields(['name' => 'Casa familiar']);
})->throws(ContentModerationUnavailableException::class);

test('it makes no request when moderation is disabled', function () {
    config()->set('services.openai.moderation_enabled', false);
    Http::fake();

    $fields = app(OpenAiContentModerator::class)->flaggedTextFields(['name' => 'Casa familiar']);

    expect($fields)->toBe([]);
    Http::assertNothingSent();
});
