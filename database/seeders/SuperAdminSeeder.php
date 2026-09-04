<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Provisions (or promotes) the account configured as `SUPERADMIN_EMAIL`.
 *
 * Created with no password, the same shape ResolveGoogleUser gives a
 * brand-new Google signup — that's what lets the real account owner sign in
 * with Google afterward and have it link to this account instead of being
 * rejected as a duplicate email. See ResolveGoogleUser for the other half of
 * that.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.superadmin_email');

        if (blank($email)) {
            throw new RuntimeException('SUPERADMIN_EMAIL must be set before seeding the superadmin account.');
        }

        $user = User::query()->where('email', $email)->first()
            ?? User::factory()->withPersonalTeam()->create([
                'name' => 'Honducasa Admin',
                'email' => $email,
                'password' => null,
            ]);

        if (! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
