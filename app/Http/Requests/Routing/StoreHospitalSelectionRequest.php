<?php

namespace App\Http\Requests\Routing;

use Illuminate\Foundation\Http\FormRequest;

class StoreHospitalSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hospital_id' => ['required', 'uuid'],
            'override_confirmation' => ['nullable', 'boolean'],
        ];
    }
}
