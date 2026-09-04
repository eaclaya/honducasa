<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('app:send-saved-search-alerts')
    ->hourly()
    ->withoutOverlapping()
    ->description('Notify users about new saved-search matches');

Schedule::command('app:pause-expired-trial-listings')
    ->hourly()
    ->withoutOverlapping()
    ->description('Pause listings for teams whose trial ended without a subscription');

Schedule::command('app:update-exchange-rates')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->description('Update exchange rates from Frankfurter');

Schedule::command('app:normalize-property-prices')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->description('Refresh normalized property prices with the latest exchange rates');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->description('Capture Horizon queue metrics');
