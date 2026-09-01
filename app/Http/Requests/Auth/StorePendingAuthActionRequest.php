<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StoreSavedSearchRequest;
use App\Rules\ContainsNoContactInformation;
use App\Support\SafeRedirectPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePendingAuthActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', Rule::in(['favorite_property', 'save_search', 'start_conversation'])],
            'payload' => ['required', 'array'],
            'redirect' => ['required', 'string', 'max:2048'],
            'payload.property_slug' => [
                Rule::excludeIf(fn (): bool => $this->input('type') !== 'favorite_property'),
                'required',
                'string',
                'max:255',
            ],
            'payload.body' => [
                Rule::excludeIf(fn (): bool => $this->input('type') !== 'start_conversation'),
                'required',
                'string',
                'min:20',
                'max:2000',
                new ContainsNoContactInformation,
            ],
        ];

        $rules['payload.property_slug'][0] = Rule::excludeIf(
            fn (): bool => ! in_array($this->input('type'), ['favorite_property', 'start_conversation'], true),
        );

        foreach (StoreSavedSearchRequest::creationRules('payload.saved_search') as $key => $savedSearchRules) {
            $rules[$key] = [
                Rule::excludeIf(fn (): bool => $this->input('type') !== 'save_search'),
                ...$savedSearchRules,
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (SafeRedirectPath::resolve($this->input('redirect')) === null) {
                    $validator->errors()->add('redirect', __('The return URL is invalid.'));
                }
            },
        ];
    }
}
