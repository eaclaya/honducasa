<?php

namespace App\Http\Requests;

use App\Enums\ConversationStatus;
use App\Enums\ListingStatus;
use App\Models\Conversation;
use App\Models\Property;
use App\Rules\ContainsNoContactInformation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');

        if ($this->user() === null
            || ! $property instanceof Property
            || $property->status !== ListingStatus::Published
            || $property->isOwnedBy($this->user())) {
            return false;
        }

        $existingStatus = Conversation::query()
            ->where('property_id', $property->id)
            ->where('renter_id', $this->user()->id)
            ->value('status');

        return $existingStatus === null
            || $existingStatus === ConversationStatus::Active
            || $existingStatus === ConversationStatus::Active->value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:20', 'max:2000', new ContainsNoContactInformation],
        ];
    }
}
