<?php

namespace App\Http\Requests;

use App\Actions\Listings\SetListingStatus;
use App\Actions\Moderation\RecordModerationStrike;
use App\Data\GeoPoint;
use App\Enums\ApproximateLocationShape;
use App\Enums\Furnishing;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use App\Exceptions\ContentModerationUnavailableException;
use App\Models\Location;
use App\Models\Property;
use App\Services\ListingTextModerationPipeline;
use App\Support\NearestCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('country_code', 'HN')],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'status' => ['required', Rule::enum(ListingStatus::class)],
            'price_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::in(array_keys(config('currencies.supported', [])))],
            'deposit_amount' => ['nullable', 'integer', 'min:0'],
            'utilities_included' => ['nullable', 'boolean'],
            'bedrooms' => $this->featureRules('bedrooms', ['integer', 'min:0', 'max:20']),
            'bathrooms' => $this->featureRules('bathrooms', ['numeric', 'min:0.5', 'max:20']),
            'parking_spaces' => $this->featureRules('parking_spaces', ['integer', 'min:0', 'max:20']),
            'interior_area_m2' => $this->featureRules('interior_area_m2', ['integer', 'min:1']),
            'lot_area_m2' => $this->featureRules('lot_area_m2', ['integer', 'min:1']),
            'year_built' => $this->featureRules('year_built', ['integer', 'min:1800', 'max:'.now()->year]),
            'furnishing' => $this->featureRules('furnishing', [Rule::enum(Furnishing::class)]),
            'description' => ['nullable', 'string', 'max:5000'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'location_mode' => ['required', Rule::in([LocationPrecision::Exact->value, LocationPrecision::Approximate->value])],
            'latitude' => ['required', 'numeric', 'between:12.9,16.6'],
            'longitude' => ['required', 'numeric', 'between:-89.4,-83.1'],
            'approximate_shape' => ['nullable', 'required_if:location_mode,approximate', Rule::enum(ApproximateLocationShape::class)],
            'approximate_radius_km' => ['nullable', 'required_if:approximate_shape,radius', 'numeric', Rule::in([0.1, 0.2, 0.3, 0.4, 0.5])],
            'approximate_polygon' => ['nullable', 'required_if:approximate_shape,polygon', 'array'],
            'approximate_polygon.type' => ['required_if:approximate_shape,polygon', Rule::in(['Polygon'])],
            'approximate_polygon.coordinates' => ['required_if:approximate_shape,polygon', 'array', 'size:1'],
            'approximate_polygon.coordinates.0' => ['required_if:approximate_shape,polygon', 'array', 'min:4', 'max:101'],
            'approximate_polygon.coordinates.0.*' => ['array', 'size:2'],
            'approximate_polygon.coordinates.0.*.0' => ['numeric', 'between:-89.4,-83.1'],
            'approximate_polygon.coordinates.0.*.1' => ['numeric', 'between:12.9,16.6'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['integer', Rule::exists('media', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'location_id.required' => 'We could not match the map pin to a city we cover yet. Move the pin closer to a supported city.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->approximate_polygon) && $this->approximate_polygon !== '') {
            $this->merge([
                'approximate_polygon' => json_decode($this->approximate_polygon, true),
            ]);
        }

        $this->clearInapplicablePropertyFields();

        $this->merge([
            'location_id' => $this->cityFromMapPin()?->getKey(),
            'status' => $this->statusAllowedByPhotos(),
        ]);
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    private function featureRules(string $field, array $rules): array
    {
        $type = PropertyType::tryFrom((string) $this->input('type'));
        $required = $type !== null && in_array($field, $type->requiredFeatureFields(), true);

        return [$required ? 'required' : 'nullable', ...$rules];
    }

    private function clearInapplicablePropertyFields(): void
    {
        $type = PropertyType::tryFrom((string) $this->input('type'));

        if ($type === null) {
            return;
        }

        $cleared = collect(PropertyType::cases())
            ->flatMap(fn (PropertyType $propertyType) => $propertyType->featureFields())
            ->unique()
            ->reject(fn (string $field) => in_array($field, $type->featureFields(), true))
            ->mapWithKeys(fn (string $field) => [$field => null])
            ->all();

        if ($this->input('listing_type') !== ListingType::Rent->value || ! $type->supportsRentalTerms()) {
            $cleared['deposit_amount'] = null;
            $cleared['utilities_included'] = null;
        }

        $this->merge($cleared);
    }

    /**
     * The city is derived from the map pin rather than picked from a dropdown.
     */
    private function cityFromMapPin(): ?Location
    {
        $point = $this->mapPin();

        return $point === null ? null : NearestCity::for($point);
    }

    /**
     * The submitted pin, or null when it is missing or off the globe — the
     * `latitude`/`longitude` rules report those, this only avoids blowing up
     * on `GeoPoint`'s range guard before validation has run.
     */
    private function mapPin(): ?GeoPoint
    {
        $latitude = $this->input('latitude');
        $longitude = $this->input('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        if (abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            return null;
        }

        return new GeoPoint((float) $latitude, (float) $longitude);
    }

    /**
     * Defer to `SetListingStatus` so the "no photos means draft" rule is
     * identical here and in the moderation console.
     */
    private function statusAllowedByPhotos(): mixed
    {
        $status = ListingStatus::tryFrom((string) $this->input('status'));

        if ($status === null) {
            return $this->input('status');
        }

        return SetListingStatus::allowedFor($status, count($this->array('images')))->value;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('approximate_shape') !== ApproximateLocationShape::Polygon->value) {
                    return;
                }

                $ring = $this->input('approximate_polygon.coordinates.0');

                if (! is_array($ring) || count($ring) < 4) {
                    return;
                }

                if ($ring[0] !== $ring[array_key_last($ring)]) {
                    $validator->errors()->add(
                        'approximate_polygon',
                        'The approximate region boundary must be closed.',
                    );
                }
            },
            function (Validator $validator): void {
                $submittedIds = collect($this->array('images'))->map(fn ($id) => (int) $id);

                if ($submittedIds->isEmpty()) {
                    return;
                }

                $listing = $this->route('listing');
                $ownedIds = $this->user()->getMedia('pending-listing-photos')->pluck('id')
                    ->merge($listing instanceof Property ? $listing->getMedia('photos')->pluck('id') : []);

                if ($submittedIds->diff($ownedIds)->isNotEmpty()) {
                    $validator->errors()->add('images', __('One of the selected photos is invalid.'));
                }
            },
            function (Validator $validator): void {
                // Skipped for precognitive (live "on change") validation —
                // moderation calls OpenAI, so it only runs on the real save
                // rather than once per keystroke/field blur.
                if ($validator->errors()->isNotEmpty() || $this->isPrecognitive()) {
                    return;
                }

                try {
                    $moderation = app(ListingTextModerationPipeline::class)->moderate([
                        'name' => $this->input('name'),
                        'description' => $this->input('description'),
                        'address_line' => $this->input('address_line'),
                    ]);
                } catch (ContentModerationUnavailableException) {
                    $validator->errors()->add(
                        'name',
                        __('Content moderation is temporarily unavailable. Please try again.'),
                    );

                    return;
                }

                $flaggedFields = $moderation['flagged_fields'];

                if ($flaggedFields !== []) {
                    app(RecordModerationStrike::class)->handle(
                        $this->user(),
                        'listing_text',
                        'Automated text moderation flagged listing content.',
                        [
                            'fields' => $flaggedFields,
                            'profanity_fields' => $moderation['profanity_fields'],
                            'openai_fields' => $moderation['openai_fields'],
                        ],
                    );
                }

                foreach ($flaggedFields as $field) {
                    $validator->errors()->add(
                        $field,
                        __('This field contains content that is not allowed.'),
                    );
                }
            },
        ];
    }
}
