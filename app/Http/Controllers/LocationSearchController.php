<?php

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Http\Requests\LocationSearchRequest;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class LocationSearchController extends Controller
{
    public function __invoke(LocationSearchRequest $request): JsonResponse
    {
        $locations = Location::query()
            ->where('country_code', 'HN')
            ->where('is_active', true)
            ->where('type', '!=', LocationType::Country)
            ->matchingSearch($request->string('q')->toString())
            ->with('parent.parent.parent')
            ->withCount([
                'properties as listing_count' => fn (Builder $query) => $query->visibleToPublic(),
            ])
            ->orderByDesc('listing_count')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type->value,
                'context' => $this->contextFor($location),
                'listingCount' => $location->getAttribute('listing_count'),
            ]);

        return response()->json(['data' => $locations]);
    }

    private function contextFor(Location $location): string
    {
        $parents = collect();
        $parent = $location->parent;

        while ($parent !== null && $parent->type !== LocationType::Country) {
            if ($parent->name !== $location->name) {
                $parents->prepend($parent->name);
            }

            $parent = $parent->parent;
        }

        return $parents->implode(', ');
    }
}
