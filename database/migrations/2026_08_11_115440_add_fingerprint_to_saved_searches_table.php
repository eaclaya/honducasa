<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->after('filters');
        });

        $filterKeys = [
            'location', 'property_type', 'listing_type', 'currency',
            'min_price', 'max_price', 'bedrooms', 'bathrooms',
            'parking_spaces', 'min_area', 'max_area', 'furnishing',
            'utilities_included', 'sort', 'latitude', 'longitude',
        ];
        $seen = [];

        DB::table('saved_searches')
            ->select(['id', 'user_id', 'filters'])
            ->orderBy('id')
            ->get()
            ->each(function (object $savedSearch) use ($filterKeys, &$seen): void {
                $filters = json_decode($savedSearch->filters, true, flags: JSON_THROW_ON_ERROR);
                $normalized = Arr::where(
                    Arr::only($filters, $filterKeys),
                    fn (mixed $value): bool => $value !== null && $value !== '',
                );

                if (($normalized['sort'] ?? null) === 'newest') {
                    unset($normalized['sort']);
                }

                ksort($normalized);
                $fingerprint = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
                $ownerFingerprint = $savedSearch->user_id.':'.$fingerprint;

                if (isset($seen[$ownerFingerprint])) {
                    DB::table('saved_searches')->where('id', $savedSearch->id)->delete();

                    return;
                }

                $seen[$ownerFingerprint] = true;
                DB::table('saved_searches')
                    ->where('id', $savedSearch->id)
                    ->update(['fingerprint' => $fingerprint]);
            });

        Schema::table('saved_searches', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable(false)->change();
            $table->unique(['user_id', 'fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
