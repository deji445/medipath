<?php

namespace App\Http\Requests\Intake;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class LookupPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => app(PhoneNumberNormalizer::class)->normalize((string) $this->input('phone_number')),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Enter a valid phone number, for example 024 123 4567.',
        ];
    }
}
