<?php

namespace App\Http\Middleware;

use App\Support\CurrencyConverter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDisplayCurrency
{
    public function __construct(private CurrencyConverter $currencyConverter) {}

    /**
     * Apply the visitor's selected display currency.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currency = $request->session()->get('display_currency');

        if (in_array($currency, $this->currencyConverter->supportedCurrencies(), true)) {
            config(['currencies.display' => $currency]);
        }

        return $next($request);
    }
}
