<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveConversationReportRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['actioned', 'dismissed'])],
            'conversation_status' => ['required', Rule::in(['active', 'blocked'])],
        ];
    }
}
