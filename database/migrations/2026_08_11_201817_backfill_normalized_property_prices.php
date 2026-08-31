<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $baseCurrency = (string) config('currencies.base');

        foreach (config('currencies.supported', []) as $currency => $settings) {
            DB::table('properties')
                ->where('currency', $currency)
                ->update([
                    'normalized_price_amount' => DB::raw('price_amount * '.(float) $settings['rate_to_base']),
                    'normalized_currency' => $baseCurrency,
                    'normalization_rate' => (string) $settings['rate_to_base'],
                    'price_normalized_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('properties')->update([
            'normalized_price_amount' => null,
            'normalized_currency' => null,
            'normalization_rate' => null,
            'price_normalized_at' => null,
        ]);
    }
};
