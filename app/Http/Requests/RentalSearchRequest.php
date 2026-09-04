<?php

namespace App\Http\Requests;

use App\Enums\Furnishing;
use App\Enums\ListingType;
use App\Enums\PropertyType;
use App\Support\PolygonQueryParameter;
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

    protected function prepareForValidation(): void
    {
        $points = PolygonQueryParameter::expand($this->input('polygon'));

        if ($points !== null) {
            $this->merge(['polygon' => $points]);
        }
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
            'currency' => ['nullable', Rule::in(array_keys(config('currencies.supported', [])))],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            // `gte:min_price` fails outright when min_price is absent, so a
            // max-only range would bounce the whole search on validation.
            'max_price' => ['nullable', 'integer', 'min:0', 'max:1000000000', Rule::when($this->filled('min_price'), ['gte:min_price'])],
            'bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'bathrooms' => ['nullable', 'numeric', 'between:0,20'],
            'parking_spaces' => ['nullable', 'integer', 'between:0,20'],
            'min_area' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_area' => ['nullable', 'integer', 'min:0', 'max:1000000', Rule::when($this->filled('min_area'), ['gte:min_area'])],
            'area_unit' => ['nullable', Rule::in(['m2', 'vara2'])],
            'furnishing' => ['nullable', Rule::enum(Furnishing::class)],
            'utilities_included' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'west' => ['nullable', 'required_with:south,east,north', 'numeric', 'between:-89.4,-83.1'],
            'south' => ['nullable', 'required_with:west,east,north', 'numeric', 'between:12.9,16.6'],
            'east' => ['nullable', 'required_with:west,south,north', 'numeric', 'between:-89.4,-83.1'],
            'north' => ['nullable', 'required_with:west,south,east', 'numeric', 'between:12.9,16.6'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'polygon' => ['nullable', 'array', 'min:4', 'max:30'],
            'polygon.*' => ['array', 'size:2'],
            'polygon.*.0' => ['numeric', 'between:-89.4,-83.1'],
            'polygon.*.1' => ['numeric', 'between:12.9,16.6'],
            'saved_search' => ['nullable', 'integer', 'min:1'],
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
            function (Validator $validator): void {
                $polygon = $this->input('polygon');

                if (! is_array($polygon) || count($polygon) < 4) {
                    return;
                }

                if ($polygon[0] !== $polygon[array_key_last($polygon)]) {
                    $validator->errors()->add('polygon', 'The drawn area boundary must be closed.');
                }
            },
        ];
    }
}
