<?php

namespace App\Enums;

enum SubscriptionLadder: string
{
    case Individual = 'individual';
    case Agency = 'agency';

    /**
     * The ladder a team shops from is derived from its type, never chosen
     * independently — a personal team is always Individual.
     */
    public static function forTeam(bool $isPersonal): self
    {
        return $isPersonal ? self::Individual : self::Agency;
    }
}
