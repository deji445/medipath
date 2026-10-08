<?php

namespace App\Http\Requests\Assessment;

use App\Enums\RedFlagCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSafetyScreenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $redFlags = array_values(array_filter((array) $this->input('red_flags', [])));

        $this->merge([
            'red_flags' => $redFlags,
            'safety_status' => $this->input('safety_status', $redFlags === [] ? 'NONE' : 'CONCERN'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'red_flags' => ['array', 'max:'.count(RedFlagCode::cases())],
            'red_flags.*' => ['distinct', Rule::enum(RedFlagCode::class)],
            'safety_status' => ['required', Rule::in(['NONE', 'CONCERN'])],
            'answers_confirmed' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answers_confirmed.accepted' => 'Choose whether an emergency warning sign may be happening now.',
        ];
    }
}
