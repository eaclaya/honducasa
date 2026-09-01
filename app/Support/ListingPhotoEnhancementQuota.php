<?php

namespace App\Support;

use App\Models\ListingPhotoEnhancement;
use App\Models\Property;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Enforces the per-listing ceiling on AI photo enhancements.
 *
 * A listing that has been saved is scoped by its own id. The create wizard has
 * no listing yet, so its usage is booked against a draft key held in the
 * session and re-parented onto the property by `claimDraft()` the moment the
 * listing is saved — that way the wizard and the subsequent edit share one
 * allowance instead of handing out five each.
 */
class ListingPhotoEnhancementQuota
{
    public const DRAFT_SESSION_KEY = 'listings.photo_enhancement_draft';

    /**
     * The draft key for the current session, minted on first use.
     *
     * Deliberately survives an abandoned wizard: it is only cleared once a
     * listing is actually saved, so reloading the form to reset the allowance
     * doesn't work.
     */
    public function draftKey(Request $request): string
    {
        $key = $request->session()->get(self::DRAFT_SESSION_KEY);

        if (! is_string($key) || $key === '') {
            $key = (string) Str::uuid();
            $request->session()->put(self::DRAFT_SESSION_KEY, $key);
        }

        return $key;
    }

    /**
     * How many enhancements have already been spent on this listing or draft.
     */
    public function used(User $user, ?Property $listing, string $draftKey): int
    {
        return $listing
            ? ListingPhotoEnhancement::query()->forListing($listing)->count()
            : ListingPhotoEnhancement::query()->forDraft($user, $draftKey)->count();
    }

    public function remaining(User $user, ?Property $listing, string $draftKey): int
    {
        return max(0, ListingPhotoEnhancement::PER_LISTING_LIMIT - $this->used($user, $listing, $draftKey));
    }

    /**
     * Run `$callback` only if the allowance has room, booking the usage before
     * it runs. Returns null when the ceiling has already been reached.
     *
     * The check and the write happen under a lock keyed on the same scope the
     * count uses, because the endpoint's `throttle:5,1` still lets five
     * requests arrive at once — without it, five concurrent enhancements each
     * read "0 used" and all five go through on a listing that had one left.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn|null
     */
    public function consume(User $user, ?Property $listing, string $draftKey, Media $media, Closure $callback): mixed
    {
        $scope = $listing ? "listing:{$listing->getKey()}" : "draft:{$user->getKey()}:{$draftKey}";

        return Cache::lock("listing-photo-enhancement-quota:{$scope}", 10)->block(5, function () use ($user, $listing, $draftKey, $media, $callback) {
            if ($this->remaining($user, $listing, $draftKey) < 1) {
                return null;
            }

            ListingPhotoEnhancement::query()->create([
                'user_id' => $user->getKey(),
                'property_id' => $listing?->getKey(),
                'draft_key' => $listing ? null : $draftKey,
                'media_id' => $media->getKey(),
            ]);

            return $callback();
        });
    }

    /**
     * Re-parent the session draft's usage onto the listing it turned into, so
     * the allowance spent in the wizard carries into editing. Called on save;
     * clearing the session key starts the next wizard with a fresh allowance.
     */
    public function claimDraft(Request $request, User $user, Property $listing): void
    {
        $draftKey = $request->session()->get(self::DRAFT_SESSION_KEY);

        if (! is_string($draftKey) || $draftKey === '') {
            return;
        }

        ListingPhotoEnhancement::query()
            ->forDraft($user, $draftKey)
            ->update(['property_id' => $listing->getKey(), 'draft_key' => null]);

        $request->session()->forget(self::DRAFT_SESSION_KEY);
    }
}
