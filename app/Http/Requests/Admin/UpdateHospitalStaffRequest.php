<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof StaffUser
            && $user->is_active
            && $user->role === StaffRole::HospitalAdmin
            && $user->hospital_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([StaffRole::Triage->value, StaffRole::Doctor->value, StaffRole::HospitalAdmin->value])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
