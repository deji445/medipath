<?php

namespace App\Http\Requests\Platform;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use App\Models\StaffUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof StaffUser && $user->is_active && $user->isSuperAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('hospitals', 'slug')],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'capability' => ['required', Rule::enum(HospitalCapability::class)],
            'routing_mode' => ['required', Rule::enum(RoutingMode::class)],
            'admin_first_name' => ['required', 'string', 'max:80'],
            'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('staff_users', 'email')],
            'admin_password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }
}
