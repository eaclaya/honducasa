<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        return $user->teams()->whereKey($property->team_id)->exists();
    }

    public function update(User $user, Property $property): bool
    {
        return $this->view($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->view($user, $property);
    }
}
