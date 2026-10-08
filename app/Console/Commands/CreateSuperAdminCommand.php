<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'medipath:create-super-admin';

    protected $description = 'Create the first active MediPath Super Admin account';

    public function handle(): int
    {
        $firstName = trim((string) $this->ask('First name'));
        $lastName = trim((string) $this->ask('Last name'));
        $email = Str::lower(trim((string) $this->ask('Email address')));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        try {
            validator([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ], [
                'first_name' => ['required', 'string', 'max:80'],
                'last_name' => ['required', 'string', 'max:80'],
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

        if (StaffUser::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error('A staff account with this email address already exists.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($firstName, $lastName, $email, $password): void {
            StaffUser::query()->create([
                'id' => (string) Str::uuid(),
                'hospital_id' => null,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => StaffRole::SuperAdmin,
                'is_active' => true,
            ]);
        });

        $this->info('Super Admin account created successfully.');

        return self::SUCCESS;
    }
}
