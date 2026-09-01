<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Carbon;

class CurrencyConverter
{
    public function baseCurrency(): string
    {
        return (string) config('currencies.base');
    }

    /** @return list<string> */
    public function supportedCurrencies(): array
    {
        return array_keys(config('currencies.supported', []));
    }

    public function rateToBase(string $currency): string
    {
        $rate = config("currencies.supported.{$currency}.rate_to_base");

        if (! is_numeric($rate) || bccomp((string) $rate, '0', 10) <= 0) {
            throw new DomainException("No valid exchange rate is configured for {$currency}.");
        }

        return (string) $rate;
    }

    public function toBase(int|float|string $amount, string $currency): string
    {
        return bcmul((string) $amount, $this->rateToBase($currency), 6);
    }

    public function fromBase(int|float|string $amount, string $currency): int
    {
        return (int) round((float) bcdiv((string) $amount, $this->rateToBase($currency), 6));
    }

    public function convert(int|float|string $amount, string $fromCurrency, string $toCurrency): int
    {
        return $this->fromBase($this->toBase($amount, $fromCurrency), $toCurrency);
    }

    /** @return array{normalized_price_amount: string, normalized_currency: string, normalization_rate: string, price_normalized_at: Carbon} */
    public function normalizationAttributes(int|float|string $amount, string $currency): array
    {
        return [
            'normalized_price_amount' => $this->toBase($amount, $currency),
            'normalized_currency' => $this->baseCurrency(),
            'normalization_rate' => $this->rateToBase($currency),
            'price_normalized_at' => now(),
        ];
    }
}
