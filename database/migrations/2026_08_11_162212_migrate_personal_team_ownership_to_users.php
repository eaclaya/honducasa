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
        $personalTeams = DB::table('teams')
            ->join('team_members', 'team_members.team_id', '=', 'teams.id')
            ->where('teams.is_personal', true)
            ->where('team_members.role', 'owner')
            ->select(['teams.id', 'teams.trial_ends_at', 'team_members.user_id'])
            ->get();

        foreach ($personalTeams as $personalTeam) {
            DB::table('users')->where('id', $personalTeam->user_id)->update([
                'current_team_id' => null,
                'individual_trial_ends_at' => $personalTeam->trial_ends_at,
            ]);

            $subscriptions = DB::table('team_subscriptions')->where('team_id', $personalTeam->id)->get();

            foreach ($subscriptions as $subscription) {
                DB::table('user_subscriptions')->insert([
                    'user_id' => $personalTeam->user_id,
                    'subscription_plan_id' => $subscription->subscription_plan_id,
                    'status' => $subscription->status,
                    'provider_customer_id' => $subscription->provider_customer_id,
                    'provider_subscription_id' => $subscription->provider_subscription_id,
                    'current_period_ends_at' => $subscription->current_period_ends_at,
                    'grace_period_ends_at' => $subscription->grace_period_ends_at,
                    'canceled_at' => $subscription->canceled_at,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                ]);
            }
        }

        $personalTeamIds = $personalTeams->pluck('id');
        DB::table('properties')->whereIn('team_id', $personalTeamIds)->update(['team_id' => null]);
        DB::table('conversations')->whereIn('team_id', $personalTeamIds)->update(['team_id' => null]);
        DB::table('teams')->whereIn('id', $personalTeamIds)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Personal teams cannot be reconstructed without inventing historical ownership data.
    }
};
