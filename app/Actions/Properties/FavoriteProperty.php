<?php

namespace App\Actions\Properties;

use App\Enums\ListingStatus;
use App\Models\Property;
use App\Models\PropertyFavorite;
use App\Models\User;

class FavoriteProperty
{
    public function handle(User $user, Property $property): PropertyFavorite
    {
        abort_unless(
            $property->status === ListingStatus::Published
                && ($property->team === null || ! $property->team->isSuspended()),
            404,
        );

        return $user->propertyFavorites()->firstOrCreate(['property_id' => $property->id]);
    }
}
