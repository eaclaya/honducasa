<?php

namespace App\Enums;

enum SubscriptionProvider: string
{
    case Stripe = 'stripe';
    case Tilopay = 'tilopay';
    case Manual = 'manual';
}
