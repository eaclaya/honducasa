<?php

namespace App\Http\Requests;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Rules\ContainsNoContactInformation;
use App\Rules\ContainsNoProfanity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            && $this->user()?->can('view', $conversation) === true
            && $conversation->status === ConversationStatus::Active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:2000', new ContainsNoContactInformation, new ContainsNoProfanity],
        ];
    }
}
