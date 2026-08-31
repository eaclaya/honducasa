<?php

namespace App\Services;

use App\Exceptions\ContentModerationUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class OpenAiContentModerator
{
    public function isEnabled(): bool
    {
        return (bool) config('services.openai.moderation_enabled');
    }

    /**
     * Return the submitted field names that OpenAI flags.
     *
     * @param  array<string, string|null>  $fields
     * @return list<string>
     */
    public function flaggedTextFields(array $fields): array
    {
        $moderatedFields = collect($fields)
            ->filter(fn (?string $value): bool => filled($value));

        if (! $this->isEnabled() || $moderatedFields->isEmpty()) {
            return [];
        }

        $results = $this->moderate($moderatedFields->values()->map(
            fn (string $text): array => ['type' => 'text', 'text' => $text],
        )->all());

        return $moderatedFields->keys()
            ->filter(fn (string $field, int $index): bool => (bool) data_get($results, "$index.flagged", false))
            ->values()
            ->all();
    }

    public function imageIsFlagged(UploadedFile $image): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $contents = file_get_contents($image->getRealPath());

        if ($contents === false) {
            throw new ContentModerationUnavailableException;
        }

        $results = $this->moderate([[
            'type' => 'image_url',
            'image_url' => [
                'url' => sprintf('data:%s;base64,%s', $image->getMimeType(), base64_encode($contents)),
            ],
        ]]);

        return (bool) data_get($results, '0.flagged', false);
    }

    /**
     * @param  list<array<string, mixed>>  $input
     * @return list<array<string, mixed>>
     */
    private function moderate(array $input): array
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new ContentModerationUnavailableException;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(20)
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/moderations', [
                    'model' => config('services.openai.moderation_model'),
                    'input' => $input,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new ContentModerationUnavailableException(previous: $exception);
        }

        $results = $response->json('results');

        if (! is_array($results) || count($results) !== count($input)) {
            throw new ContentModerationUnavailableException;
        }

        return $results;
    }
}
