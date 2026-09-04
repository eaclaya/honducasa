<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnalyticsTier;
use App\Enums\PricingModel;
use App\Enums\SupportTier;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Metadata-only: `key`, `ladder`, `price_amount`, `currency`, `provider`,
 * `provider_price_id` and `pricing_model` are reference-only per the model's
 * docblock and can't be changed here — a price or pricing-model change means
 * a new plan row, not an edit to this one. `is_active` also isn't accepted;
 * that's a state transition handled by `SubscriptionPlanController::updateActive()`.
 */
class UpdateSubscriptionPlanRequest extends FormRequest
{
    /**
     * Admin access is enforced by the route group's `EnsureUserIsAdmin`
     * middleware, so it is deliberately not repeated here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'active_listings_limit' => [
                'nullable',
                'integer',
                'min:0',
                Rule::prohibitedIf(fn () => $this->route('subscriptionPlan') instanceof SubscriptionPlan
                    && $this->route('subscriptionPlan')->pricing_model === PricingModel::PerListing),
            ],
            'seats_limit' => ['nullable', 'integer', 'min:0'],
            'featured_listing_slots' => ['required', 'integer', 'min:0'],
            'analytics_tier' => ['required', Rule::enum(AnalyticsTier::class)],
            'support_tier' => ['required', Rule::enum(SupportTier::class)],
            'is_entry_tier' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
