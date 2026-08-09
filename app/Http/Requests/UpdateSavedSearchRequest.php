<?php

namespace App\Http\Requests;

use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('saved_search') instanceof SavedSearch
            && $this->route('saved_search')->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'string', 'max:100'], 'alerts_enabled' => ['sometimes', 'boolean']];
    }
}
