<?php

namespace App\Enums;

enum LocationType: string
{
    case Country = 'country';
    case Department = 'department';
    case Municipality = 'municipality';
    case City = 'city';
    case Neighborhood = 'neighborhood';
    case Development = 'development';
}
