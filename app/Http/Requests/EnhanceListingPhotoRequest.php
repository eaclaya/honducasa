<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EnhanceListingPhotoRequest extends FormRequest
{
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

        return $isOwnedPendingPhoto || $isOwnedListingPhoto;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
