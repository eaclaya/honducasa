<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyFavoriteController extends Controller
{
    public function index(Request $request): Response
    {
        $favorites = $request->user()->propertyFavorites()
            ->with(['property.location:id,name', 'property.media'])
            ->whereHas('property', fn ($query) => $query->visibleToPublic())
            ->latest()->paginate(18)->withQueryString()
            ->through(fn ($favorite) => [
                'slug' => $favorite->property->slug,
                'name' => $favorite->property->name,
                'location' => $favorite->property->location->name,
                'priceAmount' => $favorite->property->price_amount,
                'currency' => $favorite->property->currency,
                'listingType' => $favorite->property->listing_type->value,
                'image' => $favorite->property->getFirstMediaUrl('photos', 'thumb') ?: null,
            ]);

        return Inertia::render('favorites/Index', ['favorites' => $favorites]);
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        abort_unless($property->status === ListingStatus::Published && ! $property->team->isSuspended(), 404);
        $request->user()->propertyFavorites()->firstOrCreate(['property_id' => $property->id]);

        return back();
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        $request->user()->propertyFavorites()->where('property_id', $property->id)->delete();

        return back();
    }
}
