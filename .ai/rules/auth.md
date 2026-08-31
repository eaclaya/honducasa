---
paths:
  - 'app/Http/{Responses/**,Controllers/Auth/**}'
---

# Auth

## Guest-triggered auth (AuthModal) returns via a validated `redirect` field, never raw session/input
`AuthModal.vue` (used to gate guest actions like favoriting or messaging) submits login/register with a hidden `redirect` field set to the current page URL, so the user lands back where they were instead of the dashboard. Backend support: `App\Support\SafeRedirectPath::resolve()` validates it's a same-origin relative path (starts with a single `/`, no `//`, no `://`) before it's ever passed to a redirect — this is the one and only place that check lives, don't re-implement it. It's wired into `LoginResponse`/`RegisterResponse`/`TwoFactorLoginResponse` (via `RedirectsToCurrentTeam::requestedRedirect()`, passed as `intended()`'s default so a real middleware-set `url.intended` still wins) and into the Google OAuth round-trip (`GoogleRedirectController` stashes it in session as `auth.google.redirect`, `GoogleCallbackController` pulls and revalidates it). Any new auth entry point that needs "return to this page" behavior should reuse `SafeRedirectPath::resolve()`, never trust `redirect`/`url.intended` input directly — it's a classic open-redirect vector if unvalidated.
