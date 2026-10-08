<?php

namespace App\Http\Requests\Platform;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use App\Models\StaffUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof StaffUser && $user->is_active && $user->isSuperAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hospital = $this->route('hospital');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('hospitals', 'slug')->ignore($hospital)],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'capability' => ['required', Rule::enum(HospitalCapability::class)],
            'routing_mode' => ['required', Rule::enum(RoutingMode::class)],
            'accepting_submissions' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
