<?php

namespace App\Http\Middleware;

use App\Models\Message;
use App\Support\CurrencyConverter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private CurrencyConverter $currencyConverter) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'currency' => [
                'display' => $this->currencyConverter->displayCurrency(),
                'base' => $this->currencyConverter->baseCurrency(),
                'supported' => $this->currencyConverter->supportedCurrencies(),
            ],
            'auth' => [
                'user' => $user,
            ],
            'googleOneTap' => [
                'clientId' => $user === null ? config('services.google.client_id') : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'unreadMessages' => fn () => $user ? Message::query()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)
                ->whereHas('conversation', fn ($query) => $query
                    ->where('renter_id', $user->id)
                    ->orWhereIn('team_id', $user->teams()->select('teams.id'))
                    ->orWhereHas('property', fn ($property) => $property->whereNull('team_id')->where('created_by', $user->id)))
                ->count() : 0,
            'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
        ];
    }
}
