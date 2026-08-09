<?php

namespace App\Http\Requests;

use App\Enums\Furnishing;
use App\Enums\ListingType;
use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'filters' => ['required', 'array'],
            'filters.location' => ['nullable', 'string', 'max:100'],
            'filters.property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'filters.listing_type' => ['nullable', Rule::enum(ListingType::class)],
            'filters.currency' => ['nullable', Rule::in(['HNL', 'USD'])],
            'filters.min_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'filters.max_price' => ['nullable', 'integer', 'gte:filters.min_price', 'max:1000000000'],
            'filters.bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'filters.bathrooms' => ['nullable', 'numeric', 'between:0,20'],
            'filters.parking_spaces' => ['nullable', 'integer', 'between:0,20'],
            'filters.min_area' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'filters.max_area' => ['nullable', 'integer', 'gte:filters.min_area', 'max:1000000'],
            'filters.furnishing' => ['nullable', Rule::enum(Furnishing::class)],
            'filters.utilities_included' => ['nullable', 'boolean'],
            'filters.sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'filters.latitude' => ['nullable', 'required_with:filters.longitude', 'numeric', 'between:-90,90'],
            'filters.longitude' => ['nullable', 'required_with:filters.latitude', 'numeric', 'between:-180,180'],
            'alerts_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
