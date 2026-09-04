---
paths:
  - 'app/Http/{Responses/**,Controllers/Auth/**}'
  - 'app/Actions/Auth/ResolveGoogleUser.php'
  - 'database/seeders/{SuperAdminSeeder.php,DemoPropertySeeder.php}'
  - 'app/Providers/FortifyServiceProvider.php'
---

# Auth

## Guest-triggered auth (AuthModal) returns via a validated `redirect` field, never raw session/input
`AuthModal.vue` (used to gate guest actions like favoriting or messaging) submits login/register with a hidden `redirect` field set to the current page URL, so the user lands back where they were instead of the dashboard. Backend support: `App\Support\SafeRedirectPath::resolve()` validates it's a same-origin relative path (starts with a single `/`, no `//`, no `://`) before it's ever passed to a redirect — this is the one and only place that check lives, don't re-implement it. It's wired into `LoginResponse`/`RegisterResponse`/`TwoFactorLoginResponse` (via `RedirectsToCurrentTeam::requestedRedirect()`, passed as `intended()`'s default so a real middleware-set `url.intended` still wins) and into the Google OAuth round-trip (`GoogleRedirectController` stashes it in session as `auth.google.redirect`, `GoogleCallbackController` pulls and revalidates it). Any new auth entry point that needs "return to this page" behavior should reuse `SafeRedirectPath::resolve()`, never trust `redirect`/`url.intended` input directly — it's a classic open-redirect vector if unvalidated.

## The `guest` middleware needs an explicit redirect — the framework default 500s
`Illuminate\Auth\Middleware\RedirectIfAuthenticated::defaultRedirectUri()` picks the first route named `dashboard`/`home` and calls `route()` on it with no parameters. Ours is `{current_team}/dashboard`, so the default throws `UrlGenerationException` for any user without a team — every renter, plus any agency user whose `current_team_id` is null — the moment they hit a guest route while logged in (`/login`, `/register`, the `auth/google/*` group). `FortifyServiceProvider::configureAuthenticatedRedirects()` overrides it via `RedirectIfAuthenticated::redirectUsing()`, mirroring `RedirectsToCurrentTeam`: `currentTeam ?? fallbackTeam()` → `route('dashboard', ['current_team' => slug])`, else `route('user.dashboard')`.

`SetTeamUrlDefaults` hides this in most manual testing: it sets `URL::defaults(['current_team' => ...])` whenever the user *does* have a current team, so a parameterless `route('dashboard')` silently works for them and only teamless accounts blow up. Any new `route('dashboard')` call site must pass `current_team` explicitly rather than leaning on those defaults.

## `password === null` is the deliberate signal that lets Google sign-in claim a pre-provisioned account
`ResolveGoogleUser::handle()` normally refuses to link Google to an email that already has an account (`An account already uses this email. Sign in manually before connecting Google.`) — necessary, since otherwise anyone could take over an existing password-protected account just by registering a Google identity under the victim's email. The one exception: if the existing account's `password` column is `null`, the first verified Google sign-in for that email is allowed to claim it (creates the `OauthIdentity` row and logs in as that user) instead of being rejected.

This is safe only because `password === null` never happens through any normal signup path — `Actions/Fortify/CreateNewUser` and `ResetUserPassword` always set a real password, so the only accounts in that state are ones deliberately pre-provisioned without one. Today that's exactly one case: `SuperAdminSeeder` creates the `config('app.superadmin_email')` account with `password => null` specifically so whoever owns that Google account can sign in and claim it post-seed, rather than the seeder needing to know or set a real password upfront. `DemoPropertySeeder` requires that account to already exist (via `$this->call(SuperAdminSeeder::class)`) and attributes all seeded listings to it.

If you ever add another way for an account to end up with a null password, re-check this assumption — it would silently become claimable by anyone who registers a Google account under that email.
