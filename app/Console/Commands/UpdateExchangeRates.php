<?php

namespace App\Console\Commands;

use App\Support\CurrencyConverter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('app:update-exchange-rates')]
#[Description('Update marketplace exchange rates from Frankfurter')]
class UpdateExchangeRates extends Command
{
    public function handle(CurrencyConverter $currencyConverter): int
    {
        $baseCurrency = $currencyConverter->baseCurrency();
        $currency = 'USD';

        try {
            $response = Http::baseUrl((string) config('services.frankfurter.url'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->retry([200, 500])
                ->get("rate/{$currency}/{$baseCurrency}")
                ->throw();

            $rate = $response->json('rate');

            if ($response->json('base') !== $currency || $response->json('quote') !== $baseCurrency) {
                $this->error('Frankfurter returned an unexpected currency pair. The existing rate was preserved.');

                return self::FAILURE;
            }

            $currencyConverter->storeRateToBase($currency, $rate);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Frankfurter could not provide a valid exchange rate. The existing rate was preserved.');

            return self::FAILURE;
        }

        $this->info("Updated {$currency}/{$baseCurrency} to {$currencyConverter->rateToBase($currency)}.");

        return self::SUCCESS;
    }
}
