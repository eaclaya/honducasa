<?php

namespace App\Actions\SavedSearches;

use App\Models\SavedSearch;
use App\Models\User;
use App\Support\SavedSearchFilters;

class CreateSavedSearch
{
    /**
     * @param  array{name: string, filters: array<string, mixed>, alerts_enabled?: bool}  $attributes
     */
    public function handle(User $user, array $attributes): SavedSearch
    {
        $filters = SavedSearchFilters::normalize($attributes['filters']);

        return $user->savedSearches()->createOrFirst(
            ['fingerprint' => SavedSearchFilters::fingerprint($filters)],
            [
                'name' => $attributes['name'],
                'filters' => $filters,
                'alerts_enabled' => $attributes['alerts_enabled'] ?? true,
            ],
        );
    }
}
