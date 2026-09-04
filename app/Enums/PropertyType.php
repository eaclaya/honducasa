<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case CommercialSpace = 'commercial_space';
    case Land = 'land';
    case OfficeSpace = 'office_space';
    case Warehouse = 'warehouse';
    case Building = 'building';

    /** @return list<string> */
    public function featureFields(): array
    {
        return match ($this) {
            self::House => ['bedrooms', 'bathrooms', 'parking_spaces', 'interior_area_m2', 'lot_area_m2', 'year_built', 'furnishing'],
            self::Apartment => ['bedrooms', 'bathrooms', 'parking_spaces', 'interior_area_m2', 'year_built', 'furnishing'],
            self::CommercialSpace => ['bathrooms', 'parking_spaces', 'interior_area_m2', 'lot_area_m2', 'year_built'],
            self::Land => ['lot_area_m2'],
            self::OfficeSpace => ['bathrooms', 'parking_spaces', 'interior_area_m2', 'year_built', 'furnishing'],
            self::Warehouse, self::Building => ['bathrooms', 'parking_spaces', 'interior_area_m2', 'lot_area_m2', 'year_built'],
        };
    }

    /** @return list<string> */
    public function requiredFeatureFields(): array
    {
        return match ($this) {
            self::House, self::Apartment => ['bedrooms', 'bathrooms', 'parking_spaces', 'furnishing'],
            self::Land => ['lot_area_m2'],
            self::CommercialSpace, self::OfficeSpace, self::Warehouse, self::Building => ['parking_spaces', 'interior_area_m2'],
        };
    }

    public function supportsRentalTerms(): bool
    {
        return $this !== self::Land;
    }

    /** @return array<string, array{fields: list<string>, required: list<string>, supportsRentalTerms: bool}> */
    public static function formConfiguration(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [
            $type->value => [
                'fields' => $type->featureFields(),
                'required' => $type->requiredFeatureFields(),
                'supportsRentalTerms' => $type->supportsRentalTerms(),
            ],
        ])->all();
    }
}
