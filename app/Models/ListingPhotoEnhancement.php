<?php

namespace App\Models;

use Database\Factories\ListingPhotoEnhancementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per AI photo enhancement the user asked for. Consumed on dispatch,
 * not on acceptance — a discarded candidate still cost an OpenAI call, so it
 * still counts against the listing's allowance.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $property_id
 * @property string|null $draft_key
 * @property int|null $media_id
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Property|null $property
 */
#[Fillable(['user_id', 'property_id', 'draft_key', 'media_id'])]
class ListingPhotoEnhancement extends Model
{
    /** @use HasFactory<ListingPhotoEnhancementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * How many enhancements a single listing is ever allowed.
     */
    public const PER_LISTING_LIMIT = 5;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Usage already booked against a saved listing.
     *
     * @param  Builder<ListingPhotoEnhancement>  $query
     * @return Builder<ListingPhotoEnhancement>
     */
    #[Scope]
    protected function forListing(Builder $query, Property $listing): Builder
    {
        return $query->where('property_id', $listing->getKey());
    }

    /**
     * Usage booked against a wizard draft that has not been saved yet. Scoped
     * to the user as well as the draft key so a leaked key can't spend, or
     * read, someone else's allowance.
     *
     * @param  Builder<ListingPhotoEnhancement>  $query
     * @return Builder<ListingPhotoEnhancement>
     */
    #[Scope]
    protected function forDraft(Builder $query, User $user, string $draftKey): Builder
    {
        return $query->whereNull('property_id')
            ->where('user_id', $user->getKey())
            ->where('draft_key', $draftKey);
    }
}
