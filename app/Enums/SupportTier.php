<?php

namespace App\Enums;

enum SupportTier: string
{
    case Standard = 'standard';
    case Priority = 'priority';
    case Dedicated = 'dedicated';
}
