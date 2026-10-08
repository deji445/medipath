<?php

namespace App\Http\Requests\Intake;

use App\Enums\SexAtBirth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $hasMatch = session()->has('intake.patient_id');

        return [
            'use_existing' => [$hasMatch ? 'accepted' : 'nullable'],
            'first_name' => [Rule::requiredIf(! $hasMatch), 'nullable', 'string', 'max:80'],
            'last_name' => [Rule::requiredIf(! $hasMatch), 'nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex_at_birth' => [Rule::requiredIf(! $hasMatch), 'nullable', Rule::enum(SexAtBirth::class)],
        ];
    }
}
