<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per subscription lifecycle attempt. No row exists while a team
     * is on its free trial — trial state lives on `teams.trial_ends_at` — a
     * row only appears once a team actually checks out with a provider.
     *
     * The partial unique index enforces at most one live (non-canceled)
     * subscription per team, the same single-source-of-truth discipline as
     * the rest of the admin console.
     */
    public function up(): void
    {
        Schema::create('team_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX team_subscriptions_one_live_per_team
                ON team_subscriptions (team_id)
                WHERE status != \'canceled\''
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('team_subscriptions');
    }
};
