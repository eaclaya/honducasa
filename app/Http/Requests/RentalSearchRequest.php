<?php

namespace App\Http\Requests;

use App\Enums\Furnishing;
use App\Enums\ListingType;
use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RentalSearchRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'location' => ['nullable', 'string', 'max:100'],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'listing_type' => ['nullable', Rule::enum(ListingType::class)],
            'currency' => ['nullable', Rule::in(['HNL', 'USD'])],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'max_price' => ['nullable', 'integer', 'gte:min_price', 'max:1000000000'],
            'bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'bathrooms' => ['nullable', 'numeric', 'between:0,20'],
            'parking_spaces' => ['nullable', 'integer', 'between:0,20'],
            'min_area' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_area' => ['nullable', 'integer', 'gte:min_area', 'max:1000000'],
            'furnishing' => ['nullable', Rule::enum(Furnishing::class)],
            'utilities_included' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'west' => ['nullable', 'required_with:south,east,north', 'numeric', 'between:-89.4,-83.1'],
            'south' => ['nullable', 'required_with:west,east,north', 'numeric', 'between:12.9,16.6'],
            'east' => ['nullable', 'required_with:west,south,north', 'numeric', 'between:-89.4,-83.1'],
            'north' => ['nullable', 'required_with:west,south,east', 'numeric', 'between:12.9,16.6'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled(['west', 'south', 'east', 'north'])) {
                    return;
                }

                if ($this->float('west') >= $this->float('east')) {
                    $validator->errors()->add('east', 'The east boundary must be east of the west boundary.');
                }

                if ($this->float('south') >= $this->float('north')) {
                    $validator->errors()->add('north', 'The north boundary must be north of the south boundary.');
                }
            },
        ];
    }
}
