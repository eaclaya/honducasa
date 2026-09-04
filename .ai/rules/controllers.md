---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Eager-load closures receive a Relation, not a Builder
In `->with(['relation' => fn (...) => ...])` the closure gets the relation instance (e.g. `HasMany`), not `Illuminate\Database\Eloquent\Builder` — type-hinting `Builder` there throws a TypeError at runtime (caught only when the eager load actually executes, e.g. under `paginate()`). `when()`/`withCount()` closures do receive a `Builder`, so this is easy to get wrong by copy-pasting across the two. Type-hint the specific relation class, or leave the param untyped.

## Column-limited eager loads silently blank fields checked later in PHP
`->load(['team:id,name,slug'])` only hydrates the listed columns — any other column (e.g. `suspended_at`) reads as `null`/default on that relation, with no error. A PHP check like `$property->team->isSuspended()` right after such a load will silently pass even when the real row says otherwise. When adding a new condition that reads a relation's attribute, grep for existing `relation:col,col` eager-loads of that relation and add the column, or the check quietly no-ops. Bit us once already on `PropertyShowController`'s suspension check.

## Model scopes that order silently defeat a controller's own sort
`Property::withinRadius()` appends `orderBy('distance_meters')` as part of the scope. Any `orderBy` a controller adds afterwards becomes a tiebreaker, not the sort — so `RentalSearchController`'s sort selector looked like a no-op whenever a nearby search was active (`?latitude=&longitude=`), while working fine on every other query. Call `$query->reorder()` before applying a user-chosen sort on top of a scope-built query, and grep the scopes in play for `orderBy` before assuming your sort is the one running.

The product rule behind it: "near me" is a **filter** — it narrows results to a radius and nothing more. The sort selector always decides ordering, including its `newest` default. Don't let a location constraint quietly double as an ordering.

## Display currency comes from the visitor's preference, not from a filter
Prices render in `CurrencyConverter::displayCurrency()`, which reads `config('currencies.display')` — set per request by `SetDisplayCurrency` from the `display_currency` session key that `DisplayCurrencyController` writes (same shape as the `SetLocale`/`LocaleController` pair). Controllers that render prices must call `displayCurrency()`, never `baseCurrency()`, or that page silently ignores the switcher.

`RentalSearchController` is the one place an explicit `?currency=` still wins, because `min_price`/`max_price` are denominated in the display currency: the bounds and the currency they were typed in are one unit, so `currency` stays in `SavedSearchFilters::FILTER_KEYS` (a fingerprint must tell "0–300 USD" apart from "0–300 HNL") and saved-search alerts, which run in the console with no session, keep working off the stored value. The flip side is that a stale `?currency=` would shadow a new preference, so `DisplayCurrencyController` strips that one parameter from the URL it redirects back to.
