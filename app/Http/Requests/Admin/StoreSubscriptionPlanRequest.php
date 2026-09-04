<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnalyticsTier;
use App\Enums\PricingModel;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionProvider;
use App\Enums\SupportTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:subscription_plans,key'],
            'ladder' => ['required', Rule::enum(SubscriptionLadder::class)],
            'name' => ['required', 'string', 'max:255'],
            'pricing_model' => ['required', Rule::enum(PricingModel::class)],
            'active_listings_limit' => [
                'nullable',
                'integer',
                'min:0',
                Rule::prohibitedIf(fn () => $this->input('pricing_model') === PricingModel::PerListing->value),
            ],
            'seats_limit' => ['nullable', 'integer', 'min:0'],
            'featured_listing_slots' => ['required', 'integer', 'min:0'],
            'analytics_tier' => ['required', Rule::enum(AnalyticsTier::class)],
            'support_tier' => ['required', Rule::enum(SupportTier::class)],
            'price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', Rule::in(['HNL', 'USD'])],
            'provider' => ['required', Rule::enum(SubscriptionProvider::class)],
            'provider_price_id' => ['nullable', 'string', 'max:255'],
            'is_entry_tier' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
