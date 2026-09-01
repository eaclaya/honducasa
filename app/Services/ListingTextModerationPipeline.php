<?php

namespace App\Services;

use Blaspsoft\Blasp\Facades\Blasp;

class ListingTextModerationPipeline
{
    public function __construct(private OpenAiContentModerator $openAiContentModerator) {}

    /**
     * Check local Spanish and English profanity before OpenAI safety moderation.
     *
     * @param  array<string, string|null>  $fields
     * @return array{flagged_fields: list<string>, profanity_fields: list<string>, openai_fields: list<string>}
     */
    public function moderate(array $fields): array
    {
        $moderatedFields = collect($fields)
            ->filter(fn (?string $value): bool => filled($value));

        $profanityFields = array_values($moderatedFields
            ->filter(fn (string $value): bool => Blasp::in('spanish', 'english')
                ->driver('regex')
                ->check($value)
                ->isOffensive())
            ->keys()
            ->all());

        $openAiFields = $this->openAiContentModerator->flaggedTextFields(
            $moderatedFields->all(),
        );

        return [
            'flagged_fields' => array_values(array_unique([
                ...$profanityFields,
                ...$openAiFields,
            ])),
            'profanity_fields' => $profanityFields,
            'openai_fields' => $openAiFields,
        ];
    }
}
