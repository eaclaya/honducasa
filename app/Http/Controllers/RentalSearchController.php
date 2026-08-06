<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalSearchRequest;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class RentalSearchController extends Controller
{
    /**
     * Display searchable rental inventory.
     */
    public function __invoke(RentalSearchRequest $request): Response
    {
        $filters = $request->validated();
        $location = $filters['location'] ?? null;
        $propertyType = $filters['property_type'] ?? null;

        $properties = Property::query()
            ->with('location:id,name')
            ->when($location, fn (Builder $query, string $search) => $query
                ->whereHas('location', fn (Builder $locationQuery) => $locationQuery
                    ->where('name', 'ilike', '%'.$search.'%')))
            ->when($propertyType, fn (Builder $query, string $type) => $query->where('type', $type))
            ->latest('id')
            ->paginate(18)
            ->withQueryString()
            ->through(fn (Property $property) => [
                'id' => $property->id,
                'slug' => $property->slug,
                'name' => $property->name,
                'type' => $property->type->value,
                'location' => $property->location->name,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'parkingSpaces' => $property->parking_spaces,
                'interiorAreaM2' => $property->interior_area_m2,
                'furnishing' => $property->furnishing->value,
            ]);

        return Inertia::render('rentals/Index', [
            'filters' => [
                'location' => $location ?? '',
                'propertyType' => $propertyType ?? '',
                'radius' => (int) ($filters['radius'] ?? 10),
            ],
            'properties' => $properties,
        ]);
    }
}
