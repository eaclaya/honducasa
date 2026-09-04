<?php

namespace App\Http\Controllers;

use App\Support\CurrencyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisplayCurrencyController extends Controller
{
    public function __construct(private CurrencyConverter $currencyConverter) {}

    /**
     * Store the visitor's preferred display currency.
     */
    public function __invoke(Request $request, string $currency): RedirectResponse
    {
        abort_unless(in_array($currency, $this->currencyConverter->supportedCurrencies(), true), 404);

        $request->session()->put('display_currency', $currency);

        return redirect()->to($this->previousUrlWithoutCurrencyOverride());
    }

    /**
     * The rentals search accepts `?currency=` so links and saved searches stay
     * shareable, and that parameter wins over the stored preference. Dropping it
     * on the way back is what lets the switcher take effect on that page.
     */
    private function previousUrlWithoutCurrencyOverride(): string
    {
        $previous = url()->previous();
        $parts = parse_url($previous);

        if (! isset($parts['query'])) {
            return $previous;
        }

        parse_str($parts['query'], $query);
        unset($query['currency']);

        $path = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');

        return $query === [] ? $path : $path.'?'.http_build_query($query);
    }
}
