<?php

use App\Support\CurrencyConverter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::forget('currencies.rates.USD.HNL');
    config()->set([
        'currencies.base' => 'HNL',
        'currencies.supported.USD.rate_to_base' => '24.70',
        'services.frankfurter.url' => 'https://api.frankfurter.test/v2',
    ]);
});

test('it updates the USD rate from Frankfurter', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.frankfurter.test/v2/rate/USD/HNL' => Http::response([
            'date' => '2026-09-02',
            'base' => 'USD',
            'quote' => 'HNL',
            'rate' => 26.853,
        ]),
    ]);

    $this->artisan('app:update-exchange-rates')
        ->expectsOutput('Updated USD/HNL to 26.853.')
        ->assertSuccessful();

    expect(app(CurrencyConverter::class)->rateToBase('USD'))->toBe('26.853');

    Http::assertSentCount(1);
});

test('it preserves the last successful rate when Frankfurter is unavailable', function () {
    $currencyConverter = app(CurrencyConverter::class);
    $currencyConverter->storeRateToBase('USD', '26.8000');

    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Frankfurter is unavailable'));

    $this->artisan('app:update-exchange-rates')
        ->expectsOutput('Frankfurter could not provide a valid exchange rate. The existing rate was preserved.')
        ->assertFailed();

    expect($currencyConverter->rateToBase('USD'))->toBe('26.8000');
});

test('it rejects an unexpected currency pair', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.frankfurter.test/v2/rate/USD/HNL' => Http::response([
            'date' => '2026-09-02',
            'base' => 'EUR',
            'quote' => 'HNL',
            'rate' => 31.42,
        ]),
    ]);

    $this->artisan('app:update-exchange-rates')
        ->expectsOutput('Frankfurter returned an unexpected currency pair. The existing rate was preserved.')
        ->assertFailed();

    expect(app(CurrencyConverter::class)->rateToBase('USD'))->toBe('24.70');
});

test('the exchange rate update and price normalization are scheduled daily', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('app:update-exchange-rates')
        ->expectsOutputToContain('app:normalize-property-prices')
        ->assertSuccessful();
});
