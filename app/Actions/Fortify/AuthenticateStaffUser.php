<?php

namespace App\Actions\Fortify;

use App\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticateStaffUser
{
    public function __invoke(Request $request): ?StaffUser
    {
        $email = Str::lower(trim((string) $request->input('email')));

        $staffUser = StaffUser::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if (! $staffUser?->is_active) {
            return null;
        }

        return Hash::check((string) $request->input('password'), $staffUser->getAuthPassword())
            ? $staffUser
            : null;
    }
}
