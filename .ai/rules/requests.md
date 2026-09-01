---
paths:
  - app/Http/Requests/SaveListingRequest.php
---

# Requests

## Listing city and status are derived, not submitted
`SaveListingRequest::prepareForValidation()` computes two fields the form never sends:

- `location_id` — resolved from the submitted `latitude`/`longitude` by `App\Support\NearestCity`, which only considers cities that exist as `Location` rows AND have a center in `HondurasCityCoordinates`. There is no city dropdown; both coordinates are `required` in every location mode because the city depends on them. A pin that matches no city fails the `location_id` required rule.
- `status` — a `published` submission with no `images` is silently downgraded to `draft`. Listings without photos never go public.

If you add a city to `HondurasCityCoordinates`, seed the matching `Location` row too, or listings can never be filed under it.
