<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set once at team creation (see `CreateTeam`). A team is "on trial"
     * while this is in the future and it has no active subscription —
     * existing teams get null here rather than a backdated free trial.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
