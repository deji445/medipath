<?php

namespace Tests\Feature;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use App\Enums\StaffRole;
use App\Models\ConsultantRequest;
use App\Models\Hospital;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('hospital_submissions');
        Schema::dropIfExists('consultant_requests');
        Schema::dropIfExists('staff_users');
        Schema::dropIfExists('hospitals');

        Schema::create('hospitals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('capability');
            $table->string('routing_mode');
            $table->boolean('accepting_submissions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('brand_name')->nullable();
            $table->string('brand_color')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamps();
        });

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

        Schema::create('hospital_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id');
            $table->timestamps();
        });

        Schema::create('consultant_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('normalized_phone');
            $table->text('concern')->nullable();
            $table->string('status')->default('NEW');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('hospital_id')->nullable();
            $table->string('actor_type');
            $table->uuid('actor_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->uuid('entity_id')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at');
        });
    }

    public function test_hospital_admin_can_update_only_their_hospital_settings(): void
    {
        $hospital = $this->hospital('alpha');
        $admin = $this->staff($hospital, StaffRole::HospitalAdmin, 'admin@alpha.test');

        $this->actingAs($admin)
            ->get(route('hospital.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('hospital/Settings')
                ->where('hospital.name', 'Alpha Hospital')
                ->where('hospital.walkInUrl', route('walk-in.show', $hospital))
                ->where('hospital.walkInQr', fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml')));

        $this->actingAs($admin)
            ->patch(route('hospital.settings.update'), [
                'brand_name' => 'Alpha Care',
                'address' => '1 Demo Road, Accra',
                'brand_color' => '#0F766E',
                'capability' => 'CLINIC_ONLY',
                'routing_mode' => 'MANUAL',
                'accepting_submissions' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hospitals', [
            'id' => $hospital->id,
            'brand_name' => 'Alpha Care',
            'accepting_submissions' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'hospital_id' => $hospital->id,
            'action' => 'hospital.settings.updated',
        ]);
    }

    public function test_non_admin_hospital_staff_cannot_open_hospital_controls(): void
    {
        $hospital = $this->hospital('alpha');
        $triage = $this->staff($hospital, StaffRole::Triage, 'triage@alpha.test');

        $this->actingAs($triage)->get(route('hospital.settings.edit'))->assertForbidden();
        $this->actingAs($triage)->get(route('hospital.staff.index'))->assertForbidden();
    }

    public function test_hospital_admin_can_create_staff_but_cannot_manage_another_tenant(): void
    {
        $alpha = $this->hospital('alpha');
        $beta = $this->hospital('beta');
        $admin = $this->staff($alpha, StaffRole::HospitalAdmin, 'admin@alpha.test');
        $other = $this->staff($beta, StaffRole::Doctor, 'doctor@beta.test');

        $this->actingAs($admin)
            ->post(route('hospital.staff.store'), [
                'first_name' => 'Akosua',
                'last_name' => 'Owusu',
                'email' => 'new.triage@alpha.test',
                'role' => 'TRIAGE',
                'password' => 'SecureDemo123',
                'password_confirmation' => 'SecureDemo123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_users', [
            'hospital_id' => $alpha->id,
            'email' => 'new.triage@alpha.test',
            'role' => 'TRIAGE',
        ]);

        $this->actingAs($admin)
            ->patch(route('hospital.staff.update', $other), ['role' => 'TRIAGE', 'is_active' => false])
            ->assertNotFound();

        $this->assertDatabaseHas('staff_users', ['id' => $other->id, 'role' => 'DOCTOR', 'is_active' => true]);
    }

    public function test_hospital_admin_cannot_deactivate_their_own_account(): void
    {
        $hospital = $this->hospital('alpha');
        $admin = $this->staff($hospital, StaffRole::HospitalAdmin, 'admin@alpha.test');

        $this->actingAs($admin)
            ->patch(route('hospital.staff.update', $admin), ['role' => 'HOSPITAL_ADMIN', 'is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('hospital.staff.update', $admin), ['role' => 'DOCTOR', 'is_active' => true])
            ->assertSessionHasErrors('is_active');

        $this->assertSame(StaffRole::HospitalAdmin, $admin->fresh()->role);
    }

    public function test_super_admin_can_create_a_hospital_with_its_initial_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.hospitals.store'), [
                'name' => 'North Demo Hospital',
                'slug' => 'north-demo',
                'brand_name' => 'North Care',
                'brand_color' => '#115E59',
                'address' => 'North Demo Road, Accra',
                'capability' => 'ER_CAPABLE',
                'routing_mode' => 'AUTO',
                'admin_first_name' => 'Kofi',
                'admin_last_name' => 'Asante',
                'admin_email' => 'admin@north.test',
                'admin_password' => 'SecureDemo123',
                'admin_password_confirmation' => 'SecureDemo123',
            ])
            ->assertRedirect();

        $hospital = Hospital::query()->where('slug', 'north-demo')->firstOrFail();
        $this->assertDatabaseHas('staff_users', [
            'hospital_id' => $hospital->id,
            'email' => 'admin@north.test',
            'role' => 'HOSPITAL_ADMIN',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'hospital_id' => $hospital->id,
            'action' => 'platform.hospital.created',
        ]);
    }

    public function test_platform_is_exclusive_to_super_admins(): void
    {
        $hospital = $this->hospital('alpha');
        $hospitalAdmin = $this->staff($hospital, StaffRole::HospitalAdmin, 'admin@alpha.test');

        $this->actingAs($hospitalAdmin)->get(route('platform.index'))->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->get(route('platform.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/Index')
                ->where('counts.hospitals', 1)
                ->where('hospitals.0.id', $hospital->id));
    }

    public function test_super_admin_dashboard_link_redirects_to_the_platform_workspace(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertRedirect(route('platform.index'));
    }

    public function test_super_admin_can_manage_the_consultant_callback_queue(): void
    {
        $superAdmin = $this->superAdmin();
        $consultantRequest = ConsultantRequest::query()->create([
            'name' => 'Demo Patient',
            'normalized_phone' => '+233241234567',
            'concern' => 'A non-urgent question about a headache.',
            'status' => 'NEW',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('platform.consultant-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/ConsultantRequests')
                ->where('counts.new', 1)
                ->where('requests.data.0.id', $consultantRequest->getKey())
                ->where('requests.data.0.phoneVerifiedAt', fn (mixed $value): bool => is_string($value)));

        $this->actingAs($superAdmin)
            ->patch(route('platform.consultant-requests.update', $consultantRequest), [
                'status' => 'CONTACTED',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consultant_requests', [
            'id' => $consultantRequest->getKey(),
            'status' => 'CONTACTED',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'consultant.request.status.updated',
            'entity_id' => $consultantRequest->getKey(),
        ]);
    }

    public function test_hospital_staff_cannot_access_the_platform_consultant_queue(): void
    {
        $hospital = $this->hospital('alpha');
        $doctor = $this->staff($hospital, StaffRole::Doctor, 'doctor@alpha.test');

        $this->actingAs($doctor)
            ->get(route('platform.consultant-requests.index'))
            ->assertForbidden();
    }

    private function hospital(string $slug): Hospital
    {
        return Hospital::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => ucfirst($slug).' Hospital',
            'capability' => HospitalCapability::EmergencyCapable,
            'routing_mode' => RoutingMode::Automatic,
            'accepting_submissions' => true,
            'is_active' => true,
        ]);
    }

    private function staff(Hospital $hospital, StaffRole $role, string $email): StaffUser
    {
        return StaffUser::query()->create([
            'id' => (string) Str::uuid(),
            'hospital_id' => $hospital->id,
            'email' => $email,
            'password_hash' => Hash::make('password'),
            'first_name' => 'Demo',
            'last_name' => 'Staff',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function superAdmin(): StaffUser
    {
        return StaffUser::query()->create([
            'id' => (string) Str::uuid(),
            'hospital_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password'),
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'role' => StaffRole::SuperAdmin,
            'is_active' => true,
        ]);
    }
}
