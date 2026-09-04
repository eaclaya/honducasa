<?php

namespace App\Http\Controllers;

use App\Actions\Listings\SetListingStatus;
use App\Data\GeoPoint;
use App\Enums\ApproximateLocationShape;
use App\Enums\ListingStatus;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use App\Http\Requests\SaveListingRequest;
use App\Http\Requests\UpdateListingStatusRequest;
use App\Models\Location;
use App\Models\Property;
use App\Models\Team;
use App\Support\CurrencyConverter;
use App\Support\ListingPhotoEnhancementQuota;
use App\Support\NearestCity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingController extends Controller
{
    public function __construct(
        private CurrencyConverter $currencyConverter,
        private ListingPhotoEnhancementQuota $photoEnhancementQuota,
    ) {}

    public function index(Request $request, ?Team $currentTeam = null): Response
    {
        $listings = $currentTeam?->properties()
            ?? $request->user()->createdProperties()->whereNull('team_id');

        return Inertia::render('listings/Index', [
            'listings' => $listings->with(['media', 'location:id,name'])
                ->withCount('conversations')->latest('id')
                ->paginate(18)->withQueryString()
                ->through(fn (Property $property) => [
                    'id' => $property->id, 'slug' => $property->slug, 'name' => $property->name,
                    'status' => $property->status->value, 'listingType' => $property->listing_type->value,
                    'priceAmount' => $property->price_amount, 'currency' => $property->currency,
                    'image' => $property->getFirstMediaUrl('photos', 'thumb') ?: null,
                    'location' => $property->location->name,
                    'conversationsCount' => $property->conversations_count,
                ]),
        ]);
    }

    /**
     * Show the listing wizard.
     *
     * Reachable both team-scoped (existing landlords) and team-less (first-time
     * publishers, via the `listings.start` route) — no team is created here, only
     * when the listing is actually saved, so an abandoned wizard leaves nothing behind.
     */
    public function create(Request $request, ?Team $currentTeam = null): Response|RedirectResponse
    {
        return Inertia::render('listings/Form', [
            'listing' => null,
            'locations' => $this->locations(),
            'currencies' => $this->currencyConverter->supportedCurrencies(),
            'propertyTypeFields' => PropertyType::formConfiguration(),
            'oldInput' => $request->old(),
            'photoEnhancementsRemaining' => $this->photoEnhancementQuota->remaining(
                $request->user(),
                null,
                $this->photoEnhancementQuota->draftKey($request),
            ),
        ]);
    }

    /**
     * Save an individual listing or an agency-owned listing when a team is scoped.
     */
    public function store(SaveListingRequest $request, ?Team $currentTeam = null): RedirectResponse
    {
        $requestedStatus = $request->enum('status', ListingStatus::class);
        $savedStatus = ListingStatus::Draft;

        DB::transaction(function () use ($request, $currentTeam, $requestedStatus, &$savedStatus): void {
            if ($currentTeam === null && $request->user()->individual_trial_ends_at === null) {
                $request->user()->update(['individual_trial_ends_at' => now()->addDays(30)]);
            }

            $property = Property::query()->make($this->attributes($request) + [
                'team_id' => $currentTeam?->id,
                'created_by' => $request->user()->getKey(),
                'slug' => Str::slug($request->string('name')).'-'.Str::lower(Str::random(8)),
                'status' => ListingStatus::Draft,
                'published_at' => null,
            ]);
            $property->setAttribute('coordinates', $this->coordinates($request)->toPostgisPoint());
            $property->save();
            $this->syncImages($property, $request);
            $this->photoEnhancementQuota->claimDraft($request, $request->user(), $property);
            $savedStatus = app(SetListingStatus::class)->handle($property, $requestedStatus);
        });

        if ($requestedStatus === ListingStatus::Published && $savedStatus !== ListingStatus::Published) {
            return $currentTeam === null
                ? to_route('billing.edit')->with('toast', [
                    'type' => 'warning',
                    'message' => __('You reached your active listing limit. Your property was saved as a draft. Choose a plan to publish it.'),
                ])
                : $this->redirectToBilling($currentTeam);
        }

        return to_route($currentTeam === null ? 'personal-listings.index' : 'listings.index', $currentTeam === null ? [] : $currentTeam)
            ->with('toast', ['type' => 'success', 'message' => 'Property saved successfully.']);
    }

    public function edit(Request $request, Team $currentTeam, Property $listing): Response
    {
        return $this->renderEdit($request, $listing, $currentTeam);
    }

    public function editPersonal(Request $request, Property $listing): Response
    {
        return $this->renderEdit($request, $listing);
    }

    private function renderEdit(Request $request, Property $listing, ?Team $currentTeam = null): Response
    {
        Gate::authorize('update', $listing);
        $this->ensureRouteOwnership($request, $listing, $currentTeam);
        $point = DB::table('properties')->where('id', $listing->id)->selectRaw('ST_Y(coordinates::geometry) latitude, ST_X(coordinates::geometry) longitude')->first();

        return Inertia::render('listings/Form', [
            'listing' => [
                ...$listing->toArray(),
                'address_line' => $listing->address_line,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'photos' => $listing->getMedia('photos')->map(fn (Media $media) => [
                    'id' => $media->id,
                    'url' => $media->getUrl('thumb'),
                    'name' => $media->file_name,
                    'size' => $media->size,
                ])->values(),
            ],
            'locations' => $this->locations(),
            'currencies' => $this->currencyConverter->supportedCurrencies(),
            'propertyTypeFields' => PropertyType::formConfiguration(),
            'oldInput' => $request->old(),
            'photoEnhancementsRemaining' => $this->photoEnhancementQuota->remaining(
                $request->user(),
                $listing,
                $this->photoEnhancementQuota->draftKey($request),
            ),
        ]);
    }

    public function update(SaveListingRequest $request, Team $currentTeam, Property $listing): RedirectResponse
    {
        return $this->updateListing($request, $listing, $currentTeam);
    }

    public function updatePersonal(SaveListingRequest $request, Property $listing): RedirectResponse
    {
        return $this->updateListing($request, $listing);
    }

    public function updateStatus(UpdateListingStatusRequest $request, Team $currentTeam, Property $listing, SetListingStatus $setListingStatus): RedirectResponse
    {
        return $this->updateListingStatus($request, $listing, $setListingStatus, $currentTeam);
    }

    public function updatePersonalStatus(UpdateListingStatusRequest $request, Property $listing, SetListingStatus $setListingStatus): RedirectResponse
    {
        return $this->updateListingStatus($request, $listing, $setListingStatus);
    }

    private function updateListingStatus(UpdateListingStatusRequest $request, Property $listing, SetListingStatus $setListingStatus, ?Team $currentTeam = null): RedirectResponse
    {
        Gate::authorize('update', $listing);
        $this->ensureRouteOwnership($request, $listing, $currentTeam);

        $requestedStatus = $request->enum('status', ListingStatus::class);
        $savedStatus = $setListingStatus->handle($listing, $requestedStatus);

        if ($requestedStatus === ListingStatus::Published && $savedStatus !== ListingStatus::Published) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => $listing->getMedia('photos')->isEmpty()
                    ? __('Add at least one photo before publishing this listing.')
                    : __('You reached your active listing limit. Your property was saved as a draft. Choose a plan to publish it.'),
            ]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Listing status updated.')]);
    }

    private function updateListing(SaveListingRequest $request, Property $listing, ?Team $currentTeam = null): RedirectResponse
    {
        Gate::authorize('update', $listing);
        $this->ensureRouteOwnership($request, $listing, $currentTeam);
        $requestedStatus = $request->enum('status', ListingStatus::class);
        $savedStatus = ListingStatus::Draft;

        DB::transaction(function () use ($request, $listing, $requestedStatus, &$savedStatus): void {
            $listing->update($this->attributes($request));
            $this->setCoordinates($listing, $request);
            $this->syncImages($listing, $request);
            $savedStatus = app(SetListingStatus::class)->handle($listing, $requestedStatus);
        });

        if ($requestedStatus === ListingStatus::Published && $savedStatus !== ListingStatus::Published) {
            return $currentTeam === null
                ? back()->with('toast', [
                    'type' => 'warning',
                    'message' => __('You reached your active listing limit. Your property was saved as a draft.'),
                ])
                : $this->redirectToBilling($currentTeam);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Listing updated.']);
    }

    public function destroy(Request $request, Team $currentTeam, Property $listing): RedirectResponse
    {
        return $this->destroyListing($request, $listing, $currentTeam);
    }

    public function destroyPersonal(Request $request, Property $listing): RedirectResponse
    {
        return $this->destroyListing($request, $listing);
    }

    private function destroyListing(Request $request, Property $listing, ?Team $currentTeam = null): RedirectResponse
    {
        Gate::authorize('delete', $listing);
        $this->ensureRouteOwnership($request, $listing, $currentTeam);
        $listing->delete();

        return to_route($currentTeam === null ? 'personal-listings.index' : 'listings.index', $currentTeam === null ? [] : $currentTeam);
    }

    /** @return array<string, mixed> */
    private function attributes(SaveListingRequest $request): array
    {
        $attributes = $request->safe()->except([
            'latitude',
            'longitude',
            'location_mode',
            'approximate_radius_km',
            'images',
            'status',
        ]);
        $attributes['public_location_precision'] = $request->enum('location_mode', LocationPrecision::class);
        $attributes += $this->currencyConverter->normalizationAttributes(
            $request->integer('price_amount'),
            $request->string('currency')->toString(),
        );

        if ($attributes['public_location_precision'] === LocationPrecision::Exact) {
            $attributes['approximate_shape'] = null;
            $attributes['approximate_radius_meters'] = null;
            $attributes['approximate_polygon'] = null;

            return $attributes;
        }

        $attributes['approximate_shape'] = $request->enum('approximate_shape', ApproximateLocationShape::class);
        $attributes['approximate_radius_meters'] = $attributes['approximate_shape'] === ApproximateLocationShape::Radius
            ? (int) round($request->float('approximate_radius_km') * 1_000)
            : null;
        $attributes['approximate_polygon'] = $attributes['approximate_shape'] === ApproximateLocationShape::Polygon
            ? $request->array('approximate_polygon')
            : null;

        return $attributes;
    }

    private function redirectToBilling(Team $team): RedirectResponse
    {
        return to_route('teams.billing.edit', $team)
            ->with('toast', [
                'type' => 'warning',
                'message' => __('You reached your active listing limit. Your property was saved as a draft. Choose a plan to publish it.'),
            ]);
    }

    private function ensureRouteOwnership(Request $request, Property $listing, ?Team $currentTeam): void
    {
        abort_unless(
            $currentTeam === null
                ? $listing->team_id === null && $listing->created_by === $request->user()->id
                : $listing->team_id === $currentTeam->id,
            404,
        );
    }

    private function setCoordinates(Property $property, SaveListingRequest $request): void
    {
        $point = $this->coordinates($request);

        DB::statement('UPDATE properties SET coordinates = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?', [$point->longitude, $point->latitude, $property->id]);
    }

    /**
     * Reconcile the property's `photos` collection with the media ids submitted
     * from the wizard: drop ones the user removed, move newly-uploaded pending
     * photos (see `ListingUploadController`) onto the property, and reorder
     * everything to match the order the user arranged them in.
     */
    private function syncImages(Property $property, SaveListingRequest $request): void
    {
        $submittedIds = $this->withoutSupersededOriginals(
            collect($request->input('images', []))->map(fn ($id) => (int) $id)
        );

        $property->getMedia('photos')
            ->reject(fn (Media $media) => $submittedIds->contains($media->id))
            ->each->delete();

        $existingPhotos = $property->getMedia('photos')->keyBy('id');

        $orderedMedia = $submittedIds
            ->map(function (int $id) use ($existingPhotos, $property) {
                if ($existingPhotos->has($id)) {
                    return $existingPhotos->get($id);
                }

                $moved = Media::query()->find($id)?->move($property, 'photos');
                $moved?->setCustomProperty('alt_text', $property->name)->save();

                return $moved;
            })
            ->filter();

        $orderedMedia->values()->each(
            fn (Media $media, int $index) => $media->update(['order_column' => $index + 1]),
        );
    }

    /**
     * Drop any photo whose AI-enhanced replacement is being saved alongside it,
     * and delete it outright so it can't be re-attached later.
     *
     * `EnhanceListingPhoto` stamps each candidate with the `source_media_id` it
     * was generated from, which is what makes the pairing knowable here. The
     * form already removes the original when an enhancement is accepted; this
     * is the guarantee that survives a client that didn't, which is how the
     * same room ended up rendered twice in the uploader.
     *
     * @param  SupportCollection<int, int>  $submittedIds
     * @return SupportCollection<int, int>
     */
    private function withoutSupersededOriginals(SupportCollection $submittedIds): SupportCollection
    {
        $supersededIds = Media::query()
            ->whereIn('id', $submittedIds)
            ->get()
            ->map(fn (Media $media) => $media->getCustomProperty('ai_enhanced') === true
                ? (int) $media->getCustomProperty('source_media_id')
                : 0)
            ->filter()
            ->values();

        if ($supersededIds->isEmpty()) {
            return $submittedIds;
        }

        Media::query()->whereIn('id', $supersededIds)->get()->each->delete();

        return $submittedIds->reject(fn (int $id) => $supersededIds->contains($id))->values();
    }

    /**
     * Cities the map can file a listing under, used to show publishers which one
     * their pin resolves to.
     *
     * @return Collection<int, Location>
     */
    private function locations(): Collection
    {
        return NearestCity::candidates();
    }

    private function coordinates(SaveListingRequest $request): GeoPoint
    {
        if ($request->enum('location_mode', LocationPrecision::class) === LocationPrecision::Approximate
            && $request->input('approximate_shape') === ApproximateLocationShape::Polygon->value) {
            /** @var list<array{0: float|int, 1: float|int}> $ring */
            $ring = $request->array('approximate_polygon.coordinates.0');
            $points = array_slice($ring, 0, -1);

            return new GeoPoint(
                array_sum(array_column($points, 1)) / count($points),
                array_sum(array_column($points, 0)) / count($points),
            );
        }

        return new GeoPoint($request->float('latitude'), $request->float('longitude'));
    }
}
