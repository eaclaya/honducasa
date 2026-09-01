<?php

use App\Services\ListingTextModerationPipeline;
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

test('spanish profanity is rejected before the text is checked by openai', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false],
                ['flagged' => false],
            ],
        ]),
    ]);

    $result = app(ListingTextModerationPipeline::class)->moderate([
        'name' => 'Casa en pelame la verga',
        'description' => 'Casa familiar en una zona tranquila.',
    ]);

    expect($result)->toBe([
        'flagged_fields' => ['name'],
        'profanity_fields' => ['name'],
        'openai_fields' => [],
    ]);

    Http::assertSent(fn ($request): bool => $request['input'][0]['text'] === 'Casa en pelame la verga');
});

test('the pipeline merges local profanity and openai safety results', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false],
                ['flagged' => true],
            ],
        ]),
    ]);

    $result = app(ListingTextModerationPipeline::class)->moderate([
        'name' => 'Shitty apartment',
        'description' => 'Unsafe content detected by OpenAI.',
    ]);

    expect($result)->toBe([
        'flagged_fields' => ['name', 'description'],
        'profanity_fields' => ['name'],
        'openai_fields' => ['description'],
    ]);
});

test('legitimate spanish housing vocabulary is not treated as profanity', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false],
                ['flagged' => false],
            ],
        ]),
    ]);

    $result = app(ListingTextModerationPipeline::class)->moderate([
        'name' => 'Casa apta para perros',
        'description' => 'Ideal para cualquier miembro de la comunidad.',
    ]);

    expect($result)->toBe([
        'flagged_fields' => [],
        'profanity_fields' => [],
        'openai_fields' => [],
    ]);
});
