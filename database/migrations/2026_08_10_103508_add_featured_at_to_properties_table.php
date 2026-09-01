<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A featured listing gets boosted placement. Usage against a team's
     * `featured_listing_slots` is enforced in application code rather than a
     * join table — the slot count per plan is small and bounded.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('featured_at');
        });
    }
};
