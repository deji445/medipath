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

        $portal = $request->input('portal');
        if (($portal === 'platform' && ! $staffUser->isSuperAdmin())
            || ($portal === 'hospital' && ($staffUser->isSuperAdmin() || $staffUser->hospital_id === null))
            || ($portal !== null && ! in_array($portal, ['platform', 'hospital'], true))) {
            return null;
        }

        return Hash::check((string) $request->input('password'), $staffUser->getAuthPassword())
            ? $staffUser
            : null;
    }
}
