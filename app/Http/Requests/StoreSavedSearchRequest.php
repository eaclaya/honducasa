<?php

namespace App\Http\Requests;

use App\Enums\Furnishing;
use App\Enums\ListingType;
use App\Enums\PropertyType;
use App\Support\PolygonQueryParameter;
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
        return self::creationRules();
    }

    protected function prepareForValidation(): void
    {
        $points = PolygonQueryParameter::expand($this->input('filters.polygon'));

        if ($points !== null) {
            $this->merge(['filters' => [...$this->input('filters', []), 'polygon' => $points]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function creationRules(string $prefix = ''): array
    {
        $prefix = $prefix === '' ? '' : rtrim($prefix, '.').'.';

        return [
            $prefix.'name' => ['required', 'string', 'max:100'],
            $prefix.'filters' => ['required', 'array'],
            $prefix.'filters.location' => ['nullable', 'string', 'max:100'],
            $prefix.'filters.property_type' => ['nullable', Rule::enum(PropertyType::class)],
            $prefix.'filters.listing_type' => ['nullable', Rule::enum(ListingType::class)],
            $prefix.'filters.currency' => ['nullable', Rule::in(array_keys(config('currencies.supported', [])))],
            $prefix.'filters.min_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            $prefix.'filters.max_price' => ['nullable', 'integer', 'gte:'.$prefix.'filters.min_price', 'max:1000000000'],
            $prefix.'filters.bedrooms' => ['nullable', 'integer', 'between:0,20'],
            $prefix.'filters.bathrooms' => ['nullable', 'numeric', 'between:0,20'],
            $prefix.'filters.parking_spaces' => ['nullable', 'integer', 'between:0,20'],
            $prefix.'filters.min_area' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            $prefix.'filters.max_area' => ['nullable', 'integer', 'gte:'.$prefix.'filters.min_area', 'max:1000000'],
            $prefix.'filters.furnishing' => ['nullable', Rule::enum(Furnishing::class)],
            $prefix.'filters.utilities_included' => ['nullable', 'boolean'],
            $prefix.'filters.sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            $prefix.'filters.latitude' => ['nullable', 'required_with:'.$prefix.'filters.longitude', 'numeric', 'between:-90,90'],
            $prefix.'filters.longitude' => ['nullable', 'required_with:'.$prefix.'filters.latitude', 'numeric', 'between:-180,180'],
            $prefix.'filters.polygon' => ['nullable', 'array', 'min:4', 'max:30'],
            $prefix.'filters.polygon.*' => ['array', 'size:2'],
            $prefix.'filters.polygon.*.0' => ['numeric', 'between:-89.4,-83.1'],
            $prefix.'filters.polygon.*.1' => ['numeric', 'between:12.9,16.6'],
            $prefix.'alerts_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
