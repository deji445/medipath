<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreHospitalStaffRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff_users', 'email')],
            'role' => ['required', Rule::in([StaffRole::Triage->value, StaffRole::Doctor->value, StaffRole::HospitalAdmin->value])],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }
}
