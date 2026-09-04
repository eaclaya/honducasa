---
paths:
  - '{resources/js/app.ts,resources/js/pages/**}'
---

# Frontend

## New public pages must opt out of the default AppLayout by name
`resources/js/app.ts`'s `createInertiaApp({ layout: ... })` picks a layout by matching the Inertia page component's **name string**, not by any per-page opt-in. Only `'Welcome'`, `name.startsWith('rentals/')`, `name.startsWith('properties/')`, and `name.startsWith('legal/')` return `null` (no layout); everything else — including a brand-new unauthenticated page whose route needs no `auth` middleware — silently falls into `default: return AppLayout`, the authenticated sidebar shell. This renders fine with no error, so it's easy to ship a public page wrapped in the logged-in dashboard sidebar and only notice visually.

When adding a new public-facing page (no `auth` middleware on its route), add its component name to the `null`-layout switch cases in `resources/js/app.ts` — a bare name for a one-off page, or a `name.startsWith('somedir/')` case for a whole new public page directory. The `legal/` directory is already the catch-all for standalone informational pages (Terms, Privacy, FAQ, ...) with no sidebar — put a new one there before reaching for a new top-level case.
