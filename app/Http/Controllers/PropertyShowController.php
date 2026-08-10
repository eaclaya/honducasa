<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Property;
use Inertia\Inertia;
use Inertia\Response;

class PropertyShowController extends Controller
{
    /**
     * Display a public property listing.
     */
    public function __invoke(Property $property): Response
    {
        $property->load([
            'creator:id,name',
            'media',
            'location:id,name',
            'team:id,name,slug,suspended_at',
        ]);
        abort_unless($property->status === ListingStatus::Published && ! $property->team->isSuspended(), 404);

        $mapPoint = Property::query()
            ->whereKey($property->id)
            ->selectRaw('ROUND(ST_Y(coordinates::geometry)::numeric, 2) AS latitude')
            ->selectRaw('ROUND(ST_X(coordinates::geometry)::numeric, 2) AS longitude')
            ->firstOrFail();

        $related = Property::query()
            ->with(['location:id,name', 'media'])
            ->visibleToPublic()
            ->where('location_id', $property->location_id)
            ->where('listing_type', $property->listing_type)
            ->where('type', $property->type)
            ->whereKeyNot($property->id)
            ->orderByRaw('ABS(price_amount - ?)', [$property->price_amount])
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (Property $item) => [
                'slug' => $item->slug,
                'name' => $item->name,
                'location' => $item->location->name,
                'priceAmount' => $item->price_amount,
                'currency' => $item->currency,
                'listingType' => $item->listing_type->value,
                'image' => $item->getFirstMediaUrl('photos', 'thumb') ?: null,
                'bedrooms' => $item->bedrooms,
                'bathrooms' => $item->bathrooms,
                'interiorAreaM2' => $item->interior_area_m2,
            ]);

        $user = request()->user();
        $existingConversation = $user
            ? $property->conversations()->where('renter_id', $user->id)->value('id')
            : null;

        return Inertia::render('properties/Show', [
            'property' => [
                'slug' => $property->slug,
                'name' => $property->name,
                'type' => $property->type->value,
                'listingType' => $property->listing_type->value,
                'location' => $property->location->name,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'parkingSpaces' => $property->parking_spaces,
                'interiorAreaM2' => $property->interior_area_m2,
                'lotAreaM2' => $property->lot_area_m2,
                'yearBuilt' => $property->year_built,
                'furnishing' => $property->furnishing->value,
                'description' => $property->description,
                'priceAmount' => $property->price_amount,
                'currency' => $property->currency,
                'depositAmount' => $property->deposit_amount,
                'utilitiesIncluded' => $property->utilities_included,
                'publisher' => [
                    'teamName' => $property->team->name,
                    'agentName' => $property->creator->name,
                ],
                'messaging' => [
                    'canMessage' => $user !== null && ! $user->belongsToTeam($property->team),
                    'existingConversationId' => $existingConversation,
                ],
                'isFavorited' => $user?->propertyFavorites()->where('property_id', $property->id)->exists() ?? false,
                'images' => $property->getMedia('photos')->map(fn ($media) => [
                    'url' => $media->getUrl(),
                    'altText' => $media->getCustomProperty('alt_text'),
                ]),
                'map' => [
                    'latitude' => (float) $mapPoint->getAttribute('latitude'),
                    'longitude' => (float) $mapPoint->getAttribute('longitude'),
                    'precision' => $property->public_location_precision->value,
                    'shape' => $property->approximate_shape?->value,
                    'radiusMeters' => $property->approximate_radius_meters,
                    'polygon' => $property->approximate_polygon,
                ],
            ],
            'related' => $related,
        ]);
    }
}
