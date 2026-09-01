---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Eager-load closures receive a Relation, not a Builder
In `->with(['relation' => fn (...) => ...])` the closure gets the relation instance (e.g. `HasMany`), not `Illuminate\Database\Eloquent\Builder` — type-hinting `Builder` there throws a TypeError at runtime (caught only when the eager load actually executes, e.g. under `paginate()`). `when()`/`withCount()` closures do receive a `Builder`, so this is easy to get wrong by copy-pasting across the two. Type-hint the specific relation class, or leave the param untyped.

## Column-limited eager loads silently blank fields checked later in PHP
`->load(['team:id,name,slug'])` only hydrates the listed columns — any other column (e.g. `suspended_at`) reads as `null`/default on that relation, with no error. A PHP check like `$property->team->isSuspended()` right after such a load will silently pass even when the real row says otherwise. When adding a new condition that reads a relation's attribute, grep for existing `relation:col,col` eager-loads of that relation and add the column, or the check quietly no-ops. Bit us once already on `PropertyShowController`'s suspension check.
