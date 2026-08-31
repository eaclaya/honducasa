<?php

namespace App\Console\Commands;

use App\Actions\Listings\SetListingStatus;
use App\Enums\ListingStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gives trial expiry real teeth: a team whose 30-day trial has ended without
 * converting to a subscription keeps its data but its listings stop being
 * public, the same as a suspended team.
 */
#[Signature('app:pause-expired-trial-listings')]
#[Description('Pause published listings for teams whose trial ended without a subscription')]
class PauseExpiredTrialListings extends Command
{
    public function handle(SetListingStatus $setListingStatus): int
    {
        Team::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('subscriptions', fn (Builder $query) => $query->whereNot('status', SubscriptionStatus::Canceled))
            ->with(['properties' => fn (HasMany $query) => $query->where('status', ListingStatus::Published)])
            ->chunkById(50, function ($teams) use ($setListingStatus): void {
                foreach ($teams as $team) {
                    foreach ($team->properties as $property) {
                        $setListingStatus->handle($property, ListingStatus::Paused);
                    }
                }
            });

        return self::SUCCESS;
    }
}
