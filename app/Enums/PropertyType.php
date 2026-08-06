<?php

namespace App\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case House = 'house';
    case Condominium = 'condominium';
    case Townhouse = 'townhouse';
    case Room = 'room';
    case Studio = 'studio';
}
