<?php

namespace App\Actions\Auth;

use App\Actions\Notifications\NotifyAdministrators;
use App\Enums\IdentityProvider;
use App\Models\OauthIdentity;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;

class ResolveGoogleUser
{
    public function handle(AbstractUser $googleUser): User
    {
        $subject = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $raw = $googleUser->getRaw();
        $isEmailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if ($subject === '' || $email === '' || ! $isEmailVerified) {
            throw new DomainException(__('Google did not provide a verified email address.'));
        }

        $identity = OauthIdentity::query()
            ->with('user')
            ->where('provider', IdentityProvider::Google)
            ->where('provider_subject', $subject)
            ->first();

        if ($identity) {
            $identity->update([
                'provider_email' => $email,
                'last_used_at' => now(),
            ]);

            return $identity->user;
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existingUser) {
            // A password-protected account can never link this way — that
            // would let anyone claim it just by registering a Google account
            // under the victim's email. An account with no password was
            // never secured by one to begin with (the only accounts in that
            // state are pre-provisioned ones like SuperAdminSeeder's), so
            // the first real Google sign-in for that email is safe to treat
            // as the rightful owner claiming it.
            if ($existingUser->password !== null) {
                throw new DomainException(__('An account already uses this email. Sign in manually before connecting Google.'));
            }

            $existingUser->oauthIdentities()->create([
                'provider' => IdentityProvider::Google,
                'provider_subject' => $subject,
                'provider_email' => $email,
                'linked_at' => now(),
                'last_used_at' => now(),
            ]);

            return $existingUser;
        }

        $user = DB::transaction(function () use ($googleUser, $subject, $email) {
            $name = trim((string) $googleUser->getName()) ?: Str::before($email, '@');

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $user->oauthIdentities()->create([
                'provider' => IdentityProvider::Google,
                'provider_subject' => $subject,
                'provider_email' => $email,
                'linked_at' => now(),
                'last_used_at' => now(),
            ]);

            return $user;
        });

        app(NotifyAdministrators::class)->ofNewAccount($user, 'google');

        return $user;
    }
}
