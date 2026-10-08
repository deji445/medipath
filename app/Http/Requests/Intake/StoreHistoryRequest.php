<?php

namespace App\Http\Requests\Intake;

use Illuminate\Foundation\Http\FormRequest;

class StoreHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'conditions_text' => ['nullable', 'string', 'max:2000'],
            'allergies_text' => ['nullable', 'string', 'max:2000'],
            'medications_text' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
