<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map of retired `PropertyType` values to their closest surviving case,
     * from the catalog overhaul that replaced Condominium/Townhouse/Room/
     * Studio with Land/CommercialSpace/OfficeSpace/Warehouse/Building.
     * Existing rows still carry the old strings, which the `type` column's
     * enum cast can no longer hydrate.
     *
     * @var array<string, string>
     */
    private const array LEGACY_TYPE_MAP = [
        'condominium' => 'apartment',
        'townhouse' => 'house',
        'room' => 'apartment',
        'studio' => 'apartment',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LEGACY_TYPE_MAP as $legacy => $current) {
            DB::table('properties')->where('type', $legacy)->update(['type' => $current]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible: the original type distinctions no longer exist in the
     * enum, so there is nothing meaningful to roll back to.
     */
    public function down(): void {}
};
