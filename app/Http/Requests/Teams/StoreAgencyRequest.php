<?php

namespace App\Http\Requests\Teams;

use App\Enums\SubscriptionLadder;
use App\Rules\TeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new TeamName],
            'subscription_plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')
                    ->where('is_active', true)
                    ->where('ladder', SubscriptionLadder::Agency->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The agency name is required.'),
            'subscription_plan_id.required' => __('Select an agency plan.'),
            'subscription_plan_id.exists' => __('The selected agency plan is no longer available.'),
        ];
    }
}
