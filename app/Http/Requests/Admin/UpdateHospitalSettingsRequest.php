<?php

namespace App\Http\Requests\Admin;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalSettingsRequest extends FormRequest
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
            'brand_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'capability' => ['required', Rule::enum(HospitalCapability::class)],
            'routing_mode' => ['required', Rule::enum(RoutingMode::class)],
            'accepting_submissions' => ['required', 'boolean'],
        ];
    }
}
