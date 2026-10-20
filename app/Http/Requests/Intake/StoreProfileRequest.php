<?php

namespace App\Http\Requests\Intake;

use App\Enums\SexAtBirth;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $hasMatch = $this->session()->has('intake.patient_id');
        $patientId = $this->session()->get('intake.patient_id');
        $savedSex = is_string($patientId)
            ? Patient::query()->whereKey($patientId)->value('sex_at_birth')
            : null;
        $needsSexAtBirth = ! $hasMatch || in_array($savedSex, [null, SexAtBirth::Unknown->value], true);

        return [
            'use_existing' => ['nullable', 'boolean'],
            'first_name' => [Rule::requiredIf(! $hasMatch), 'nullable', 'string', 'max:80'],
            'last_name' => [Rule::requiredIf(! $hasMatch), 'nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex_at_birth' => [Rule::requiredIf($needsSexAtBirth), 'nullable', Rule::enum(SexAtBirth::class)],
            'history_mode' => ['required', Rule::in($hasMatch ? ['UNCHANGED', 'UPDATE'] : ['UPDATE'])],
            'conditions_status' => ['required_if:history_mode,UPDATE', 'nullable', Rule::in(['NONE', 'DETAILS', 'UNSURE'])],
            'conditions_text' => ['nullable', 'string', 'max:2000'],
            'allergies_status' => ['required_if:history_mode,UPDATE', 'nullable', Rule::in(['NONE', 'DETAILS', 'UNSURE'])],
            'allergies_text' => ['nullable', 'string', 'max:2000'],
            'medications_status' => ['required_if:history_mode,UPDATE', 'nullable', Rule::in(['NONE', 'DETAILS', 'UNSURE'])],
            'medications' => ['nullable', 'array', 'max:20'],
            'medications.*.name' => ['nullable', 'string', 'max:160'],
            'medications.*.dose' => ['nullable', 'string', 'max:100'],
            'medications.*.frequency' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'history_mode.required' => 'Choose whether the saved medical history is still correct.',
        ];
    }
}
