---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Admin console: authorization and the write vocabulary
Admin access is granted by `EnsureUserIsAdmin` on the `admin/` route group in routes/web.php. Do NOT repeat the check inside admin controllers or form requests (`authorize()` returns true there), and do NOT add a `Gate::before` for admins — that would bypass the team-ownership checks in PropertyPolicy/ConversationPolicy on every non-admin route too.

Admins get state transitions, never business-field editing. They may change status/visibility (publish, pause, archive, suspend, redact, block) but must never write price, description, email, coordinates or memberships. Every transition:
- goes through an Action class shared with the user-facing path, so invariants hold. `SetListingStatus::allowedFor()` is the single home of "a listing with no photos can never be published" and is called by both `SaveListingRequest` and the console.
- takes a reason and is recorded via `RecordAdminActivity` into `admin_activities` (append-only; no updated_at).
- is reversible. Nothing hard deletes: Property and Team use SoftDeletes, users get `suspended_at`, messages get `redacted_at` with `body` preserved as evidence.

## Team route-model binding uses slug, not id
`Team::getRouteKeyName()` returns `slug`, so every `{team}` route param — including new ones under `admin/teams/**` — binds by slug. Wayfinder-generated route helpers for these routes type their arg as `string | {slug: string}`, not a numeric id. Pass `team.slug` from the frontend, not `team.id` (User and Property both bind by id, so this is specific to Team and easy to get backwards when writing sibling admin pages).
