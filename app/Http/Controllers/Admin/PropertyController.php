<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAdminActivity;
use App\Actions\Listings\SetListingStatus;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePropertyStatusRequest;
use App\Models\Property;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $teamId = $request->integer('team_id') ?: null;
        $listingType = $request->string('listing_type')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;
        $noPhotos = $request->boolean('no_photos');

        $properties = Property::query()
            ->with(['team:id,name,slug', 'location:id,name'])
            ->withCount(['media as photos_count' => fn (Builder $query) => $query->where('collection_name', 'photos')])
            ->when($search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$search}%"))
            ))
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($teamId, fn (Builder $query, int $teamId) => $query->where('team_id', $teamId))
            ->when($listingType, fn (Builder $query, string $listingType) => $query->where('listing_type', $listingType))
            ->when($type, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($noPhotos, fn (Builder $query) => $query->doesntHave('media'))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Property $property) => [
                'id' => $property->id,
                'slug' => $property->slug,
                'name' => $property->name,
                'status' => $property->status->value,
                'type' => $property->type->value,
                'listingType' => $property->listing_type->value,
                'priceAmount' => $property->price_amount,
                'currency' => $property->currency,
                'photosCount' => $property->photos_count,
                'teamName' => $property->team->name,
                'teamSlug' => $property->team->slug,
                'locationName' => $property->location->name,
                'publishedAt' => $property->published_at?->translatedFormat('d M Y'),
                'createdAt' => $property->created_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('admin/properties/Index', [
            'properties' => $properties,
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'facetCounts' => [
                'all' => Property::query()->count(),
                'published' => Property::query()->where('status', ListingStatus::Published)->count(),
                'draft' => Property::query()->where('status', ListingStatus::Draft)->count(),
                'paused' => Property::query()->where('status', ListingStatus::Paused)->count(),
                'archived' => Property::query()->where('status', ListingStatus::Archived)->count(),
            ],
            'filters' => compact('search', 'status', 'teamId', 'listingType', 'type', 'noPhotos'),
        ]);
    }

    public function updateStatus(UpdatePropertyStatusRequest $request, Property $property): RedirectResponse
    {
        $requested = ListingStatus::from($request->validated('status'));
        $from = $property->status;

        $status = app(SetListingStatus::class)->handle($property, $requested);

        app(RecordAdminActivity::class)->handle(
            $request->user(),
            'property.status_changed',
            $property,
            $request->validated('reason'),
            ['status' => ['from' => $from->value, 'to' => $status->value]],
        );

        return back();
    }
}
