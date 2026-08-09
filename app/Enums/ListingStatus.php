<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Archived = 'archived';
}
