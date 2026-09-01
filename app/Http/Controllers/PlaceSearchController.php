<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceSearchRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Free-text place search for the listing wizard's map (colonias, landmarks,
 * hospitals, etc.), proxied to a self-hosted Nominatim instance because
 * Mapbox's own geocoding data for Honduras is too thin for these queries.
 *
 * Response is reshaped to match the GeoJSON-like feature shape
 * `PropertyLocationPicker.vue` already expects, so it's a drop-in replacement
 * for the Mapbox call it used to make directly from the browser.
 */
class PlaceSearchController extends Controller
{
    public function __invoke(PlaceSearchRequest $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $features = Cache::remember(
            'place-search:'.md5($query),
            now()->addHour(),
            fn () => $this->search($query),
        );

        return response()->json(['features' => $features]);
    }

    /** @return list<array<string, mixed>> */
    private function search(string $query): array
    {
        try {
            $response = Http::connectTimeout(2)->timeout(5)->get(
                rtrim(config('services.nominatim.url'), '/').'/search',
                [
                    'q' => $query,
                    'format' => 'json',
                    'countrycodes' => 'hn',
                    'limit' => 5,
                    'accept-language' => app()->getLocale(),
                ],
            );
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $result): array => [
                'id' => (string) $result['place_id'],
                'geometry' => [
                    'coordinates' => [(float) $result['lon'], (float) $result['lat']],
                ],
                'properties' => [
                    'name' => filled($result['name'] ?? null) ? $result['name'] : Str::before($result['display_name'], ','),
                    'full_address' => $result['display_name'],
                ],
            ])
            ->all();
    }
}
