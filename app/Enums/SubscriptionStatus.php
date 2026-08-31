<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Incomplete = 'incomplete';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
}
