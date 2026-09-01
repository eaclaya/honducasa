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

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->description('Capture Horizon queue metrics');
