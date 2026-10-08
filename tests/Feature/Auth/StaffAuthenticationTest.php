<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\AuthenticateStaffUser;
use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAuthenticationTest extends TestCase
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

    public function test_active_staff_can_authenticate_with_email_case_insensitively(): void
    {
        $staffUser = $this->createStaffUser(isActive: true);

        $authenticated = app(AuthenticateStaffUser::class)(Request::create('/login', 'POST', [
            'email' => 'STAFF@MEDIPATH.TEST',
            'password' => 'correct-password',
        ]));

        $this->assertTrue($staffUser->is($authenticated));
    }

    public function test_inactive_staff_cannot_authenticate(): void
    {
        $this->createStaffUser(isActive: false);

        $authenticated = app(AuthenticateStaffUser::class)(Request::create('/login', 'POST', [
            'email' => 'staff@medipath.test',
            'password' => 'correct-password',
        ]));

        $this->assertNull($authenticated);
    }

    public function test_incorrect_password_is_rejected(): void
    {
        $this->createStaffUser(isActive: true);

        $authenticated = app(AuthenticateStaffUser::class)(Request::create('/login', 'POST', [
            'email' => 'staff@medipath.test',
            'password' => 'incorrect-password',
        ]));

        $this->assertNull($authenticated);
    }

    public function test_public_registration_route_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    private function createStaffUser(bool $isActive): StaffUser
    {
        return StaffUser::query()->create([
            'id' => (string) Str::uuid(),
            'hospital_id' => null,
            'email' => 'staff@medipath.test',
            'password_hash' => Hash::make('correct-password'),
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'role' => StaffRole::SuperAdmin,
            'is_active' => $isActive,
        ]);
    }
}
