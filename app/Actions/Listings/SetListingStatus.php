<?php

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * The single place a listing's status changes.
 *
 * Both the landlord's own form (`SaveListingRequest`) and the moderation
 * console go through `allowedFor()`, so neither can put a listing without
 * photos, or past the team's plan limit, in front of the public — the rules
 * live here rather than in whichever controller happens to be writing.
 */
class SetListingStatus
{
    /**
     * Apply a status to a persisted listing, returning the status actually set.
     */
    public function handle(Property $property, ListingStatus $requested): ListingStatus
    {
        return DB::transaction(function () use ($property, $requested): ListingStatus {
            $property->refresh();
            $property->team_id === null
                ? $property->creator()->lockForUpdate()->firstOrFail()
                : $property->team()->lockForUpdate()->firstOrFail();

            // Only a transition into Published claims a slot against the plan
            // limit — re-saving an already-published listing doesn't need room.
            $enteringPublished = $requested === ListingStatus::Published && $property->status !== ListingStatus::Published;
            $withinPlanLimit = ! $enteringPublished || $this->canPublishAnotherListing($property);

            $status = self::allowedFor($requested, $property->getMedia('photos')->count(), $withinPlanLimit);

            $property->update([
                'status' => $status,
                'published_at' => $status === ListingStatus::Published
                    ? ($property->published_at ?? now())
                    : null,
            ]);

            return $status;
        });
    }

    private function canPublishAnotherListing(Property $property): bool
    {
        if ($property->team !== null) {
            return $property->team->canPublishAnotherListing();
        }

        return $property->creator->canPublishAnotherIndividualListing();
    }

    /**
     * A listing with no photos can never be public, and neither can one that
     * would push its team past its plan's listing limit — either downgrades
     * a publish request to a draft rather than rejecting it outright.
     */
    public static function allowedFor(ListingStatus $requested, int $photoCount, bool $withinPlanLimit = true): ListingStatus
    {
        return $requested === ListingStatus::Published && ($photoCount === 0 || ! $withinPlanLimit)
            ? ListingStatus::Draft
            : $requested;
    }
}
