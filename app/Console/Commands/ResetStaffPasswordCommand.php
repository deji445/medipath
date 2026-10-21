<?php

namespace App\Console\Commands;

use App\Models\StaffUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ResetStaffPasswordCommand extends Command
{
    protected $signature = 'medipath:reset-staff-password
        {email : Staff email address}
        {--password= : New password; omit this option to enter it securely}';

    protected $description = 'Reset an existing MediPath staff account password';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $passwordOption = $this->option('password');
        $password = is_string($passwordOption) && $passwordOption !== ''
            ? $passwordOption
            : (string) $this->secret('New password (minimum 12 characters)');
        $confirmation = is_string($passwordOption) && $passwordOption !== ''
            ? $password
            : (string) $this->secret('Confirm password');

        try {
            validator([
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'email' => ['required', 'email:rfc', 'max:255'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->mixedCase()->letters()->numbers(),
                ],
            ])->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $staffUser = StaffUser::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if (! $staffUser instanceof StaffUser) {
            $this->error('No staff account was found for that email address.');

            return self::FAILURE;
        }

        $staffUser->forceFill([
            'password_hash' => Hash::make($password),
        ])->save();

        $this->info('Staff password reset successfully.');

        return self::SUCCESS;
    }
}
