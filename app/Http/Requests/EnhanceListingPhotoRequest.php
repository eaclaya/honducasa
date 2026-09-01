<?php

namespace App\Http\Requests;

use App\Models\ListingPhotoEnhancement;
use App\Models\Property;
use App\Support\ListingPhotoEnhancementQuota;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EnhanceListingPhotoRequest extends FormRequest
{
    private ?Property $resolvedListing = null;

    private bool $listingResolved = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $media = $this->route('media');

        if (! $media instanceof Media) {
            return false;
        }

        $isOwnedPendingPhoto = $media->collection_name === 'pending-listing-photos'
            && $media->model_type === $this->user()?->getMorphClass()
            && $media->model_id === $this->user()?->getKey();

        $isOwnedListingPhoto = $media->collection_name === 'photos'
            && $media->model instanceof Property
            && $this->user()?->can('update', $media->model);

        // A `listing` that names a property the user can't edit is rejected
        // rather than ignored: silently falling back to the draft allowance
        // would let anyone spend past an exhausted listing by sending junk.
        if ($this->has('listing') && $this->listing() === null) {
            return false;
        }

        return $isOwnedPendingPhoto || $isOwnedListingPhoto;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'listing' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $quota = app(ListingPhotoEnhancementQuota::class);
                $user = $this->user();

                if ($user === null) {
                    return;
                }

                if ($quota->remaining($user, $this->listing(), $quota->draftKey($this)) < 1) {
                    $validator->errors()->add('media', __('This listing has already used all :limit AI photo enhancements.', [
                        'limit' => ListingPhotoEnhancement::PER_LISTING_LIMIT,
                    ]));
                }
            },
        ];
    }

    /**
     * The listing this enhancement is charged to, or null while the create
     * wizard has no saved listing yet.
     *
     * A photo already on a listing names its own property. A pending upload
     * doesn't — the form sends `listing` so an edit-mode enhancement lands on
     * that listing's allowance instead of a draft one.
     */
    public function listing(): ?Property
    {
        if ($this->listingResolved) {
            return $this->resolvedListing;
        }

        $this->listingResolved = true;
        $media = $this->route('media');

        if ($media instanceof Media
            && $media->collection_name === 'photos'
            && $media->model instanceof Property) {
            return $this->resolvedListing = $media->model;
        }

        $listingId = (int) $this->input('listing');

        if ($listingId < 1) {
            return $this->resolvedListing = null;
        }

        $listing = Property::query()->find($listingId);

        return $this->resolvedListing = $listing !== null && $this->user()?->can('update', $listing)
            ? $listing
            : null;
    }
}
