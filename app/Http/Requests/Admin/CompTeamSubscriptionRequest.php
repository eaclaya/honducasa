<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompTeamSubscriptionRequest extends FormRequest
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
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
