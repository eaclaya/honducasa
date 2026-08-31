---
paths:
  - 'app/Http/Requests/Admin/*SubscriptionPlan*.php'
---

# Requests Admin

## SubscriptionPlan CRUD: edit is metadata-only, price/provider never move
`SubscriptionPlanController::update()` (via `UpdateSubscriptionPlanRequest`) only accepts name, limits (active_listings_limit, seats_limit, featured_listing_slots), analytics_tier, support_tier, is_entry_tier, sort_order. It must never accept key, ladder, price_amount, currency, provider, or provider_price_id — those are reference-only per the model/migration docblocks, and a price/provider change means creating a new plan row via `store()`, not editing this one. `is_active` is also excluded from update(); that stays on the separate `updateActive()` state-transition endpoint. If you're tempted to add these fields to the edit form to "simplify" the UI, don't — it breaks the immutability invariant relied on once real subscribers/provider prices exist.
