<?php

namespace Tests\Feature\Console;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResetStaffPasswordCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('staff_users');
        Schema::create('staff_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id')->nullable();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        StaffUser::query()->create([
            'id' => '60e83dc7-5262-44be-9e89-e401764220da',
            'hospital_id' => null,
            'email' => 'admin@medipath.test',
            'password_hash' => Hash::make('ExistingPassword123'),
            'first_name' => 'Existing',
            'last_name' => 'Admin',
            'role' => StaffRole::SuperAdmin,
            'is_active' => true,
        ]);
    }

    public function test_it_resets_an_existing_staff_password(): void
    {
        $this->artisan('medipath:reset-staff-password', [
            'email' => 'ADMIN@MEDIPATH.TEST',
            '--password' => 'NewSecurePassword123',
        ])->expectsOutput('Staff password reset successfully.')
            ->assertSuccessful();

        $staffUser = StaffUser::query()->sole();

        $this->assertTrue(Hash::check('NewSecurePassword123', $staffUser->password_hash));
        $this->assertFalse(Hash::check('ExistingPassword123', $staffUser->password_hash));
    }

    public function test_it_fails_without_changing_data_for_an_unknown_email(): void
    {
        $originalHash = StaffUser::query()->sole()->password_hash;

        $this->artisan('medipath:reset-staff-password', [
            'email' => 'missing@medipath.test',
            '--password' => 'NewSecurePassword123',
        ])->expectsOutput('No staff account was found for that email address.')
            ->assertFailed();

        $this->assertSame($originalHash, StaffUser::query()->sole()->password_hash);
    }
}
