<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Support\CurrencyConverter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:normalize-property-prices')]
#[Description('Refresh normalized property prices using the configured exchange rates')]
class NormalizePropertyPrices extends Command
{
    public function handle(CurrencyConverter $currencyConverter): int
    {
        $updated = 0;

        Property::query()
            ->select(['id', 'price_amount', 'currency'])
            ->chunkById(500, function ($properties) use ($currencyConverter, &$updated): void {
                foreach ($properties as $property) {
                    $property->forceFill($currencyConverter->normalizationAttributes(
                        $property->price_amount,
                        $property->currency,
                    ))->saveQuietly();
                    $updated++;
                }
            });

        $this->info("Normalized {$updated} property prices into {$currencyConverter->baseCurrency()}.");

        return self::SUCCESS;
    }
}
