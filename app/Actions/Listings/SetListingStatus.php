<?php

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Models\Property;

/**
 * The single place a listing's status changes.
 *
 * Both the landlord's own form (`SaveListingRequest`) and the moderation
 * console go through `allowedFor()`, so neither can put a listing without
 * photos in front of the public — the rule lives here rather than in whichever
 * controller happens to be writing.
 */
class SetListingStatus
{
    /**
     * Apply a status to a persisted listing, returning the status actually set.
     */
    public function handle(Property $property, ListingStatus $requested): ListingStatus
    {
        $status = self::allowedFor($requested, $property->getMedia('photos')->count());

        $property->update([
            'status' => $status,
            'published_at' => $status === ListingStatus::Published
                ? ($property->published_at ?? now())
                : null,
        ]);

        return $status;
    }

    /**
     * A listing with no photos can never be public, so a publish request for
     * one is downgraded to a draft rather than rejected.
     */
    public static function allowedFor(ListingStatus $requested, int $photoCount): ListingStatus
    {
        return $requested === ListingStatus::Published && $photoCount === 0
            ? ListingStatus::Draft
            : $requested;
    }
}
