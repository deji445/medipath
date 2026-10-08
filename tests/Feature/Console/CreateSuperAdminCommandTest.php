<?php

namespace Tests\Feature\Console;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
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
    }

    public function test_it_creates_an_active_super_admin_with_a_hashed_password(): void
    {
        $this->artisan('medipath:create-super-admin')
            ->expectsQuestion('First name', 'Ama')
            ->expectsQuestion('Last name', 'Mensah')
            ->expectsQuestion('Email address', 'ADMIN@MEDIPATH.TEST')
            ->expectsQuestion('Password (minimum 12 characters)', 'SecurePassword123')
            ->expectsQuestion('Confirm password', 'SecurePassword123')
            ->expectsOutput('Super Admin account created successfully.')
            ->assertSuccessful();

        $staffUser = StaffUser::query()->sole();

        $this->assertSame('admin@medipath.test', $staffUser->email);
        $this->assertSame(StaffRole::SuperAdmin, $staffUser->role);
        $this->assertNull($staffUser->hospital_id);
        $this->assertTrue($staffUser->is_active);
        $this->assertTrue(Hash::check('SecurePassword123', $staffUser->password_hash));
        $this->assertNotSame('SecurePassword123', $staffUser->password_hash);
    }

    public function test_it_rejects_an_existing_email_case_insensitively(): void
    {
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

        $this->artisan('medipath:create-super-admin')
            ->expectsQuestion('First name', 'Ama')
            ->expectsQuestion('Last name', 'Mensah')
            ->expectsQuestion('Email address', 'ADMIN@MEDIPATH.TEST')
            ->expectsQuestion('Password (minimum 12 characters)', 'SecurePassword123')
            ->expectsQuestion('Confirm password', 'SecurePassword123')
            ->expectsOutput('A staff account with this email address already exists.')
            ->assertFailed();

        $this->assertSame(1, StaffUser::query()->count());
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('medipath:create-super-admin')
            ->expectsQuestion('First name', 'Ama')
            ->expectsQuestion('Last name', 'Mensah')
            ->expectsQuestion('Email address', 'admin@medipath.test')
            ->expectsQuestion('Password (minimum 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertSame(0, StaffUser::query()->count());
    }
}
