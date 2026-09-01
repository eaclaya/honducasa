---
paths:
  - 'app/{Actions/Teams/SubscribeToPlan.php,Http/Controllers/Teams/TeamBillingController.php}'
---

# Teams

## Team billing page is self-serve with no payment provider wired
`settings/teams/{team}/billing` (teams/Billing.vue) lets a team owner/admin (TeamPermission::UpdateTeam) pick a plan and it activates immediately via `SubscribeToPlan` — no Stripe/Tilopay checkout, no payment collected. This was a deliberate scope decision (no payment provider integrated yet), not an oversight. `SubscribeToPlan::handle()` cancels any existing live subscription and creates a new Active one, respecting the one-live-subscription-per-team partial unique index. If real payment gets wired up later, this self-serve "activate on selection" behavior needs to be gated behind an actual checkout/webhook flow — don't assume selecting a paid plan should keep activating for free.
