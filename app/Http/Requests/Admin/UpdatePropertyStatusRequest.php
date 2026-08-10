<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ListingStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
