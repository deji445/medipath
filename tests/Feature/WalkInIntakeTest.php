<?php

namespace Tests\Feature;

use App\Models\Hospital;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WalkInIntakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

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
    }

    public function test_a_hospital_qr_link_opens_a_branded_walk_in_start(): void
    {
        $hospital = $this->hospital();

        $this->get(route('walk-in.show', $hospital))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intake/WalkIn')
                ->where('hospital.name', 'Demo Care')
                ->where('hospital.capability', 'ER_CAPABLE')
                ->where('startUrl', route('walk-in.start', $hospital)));
    }

    public function test_starting_from_the_qr_preserves_the_hospital_and_arrival_type(): void
    {
        $hospital = $this->hospital();

        $this->post(route('walk-in.start', $hospital))
            ->assertRedirect(route('intake.start'))
            ->assertSessionHas('intake.preselected_hospital_id', $hospital->getKey())
            ->assertSessionHas('intake.arrival_type', 'WALK_IN_QR');

        $this->post(route('intake.lookup'), ['phone_number' => '024 123 4567'])
            ->assertSessionHas('intake.preselected_hospital_id', $hospital->getKey())
            ->assertSessionHas('intake.arrival_type', 'WALK_IN_QR');
    }

    public function test_an_unavailable_hospital_cannot_start_a_walk_in_intake(): void
    {
        $hospital = $this->hospital(['accepting_submissions' => false]);

        $this->get(route('walk-in.show', $hospital))->assertNotFound();
        $this->post(route('walk-in.start', $hospital))->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function hospital(array $overrides = []): Hospital
    {
        return Hospital::query()->create([
            'id' => '30000000-0000-4000-8000-000000000009',
            'slug' => 'demo-care',
            'name' => 'Demo Care Hospital',
            'brand_name' => 'Demo Care',
            'capability' => 'ER_CAPABLE',
            'routing_mode' => 'AUTO',
            'accepting_submissions' => true,
            'is_active' => true,
            'address' => 'Demo Road, Accra',
            ...$overrides,
        ]);
    }
}
