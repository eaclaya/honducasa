<?php

namespace App\Enums;

enum PricingModel: string
{
    case Tiered = 'tiered';
    case PerListing = 'per_listing';
}
