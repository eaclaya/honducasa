---
paths:
  - 'resources/js/pages/listings/**'
---

# Listings

## Listing form is a wizard only when creating
`listings/Form.vue` serves both create and edit. The 3-step wizard (step nav, Next/Back, per-step `validate()`) runs only when `listing === null`; editing renders every section at once with a single "Save changes" bar.

Gate on `isWizard` / `showsStep(n)` — never on `currentStep` directly — or edit-mode sections will be hidden. The same applies to `handleEnterKey` (Enter must submit on the full page) and to `PropertyLocationPicker`'s `:visible` prop, which it uses to call `map.invalidateSize()` once the map stops being `display:none`.
