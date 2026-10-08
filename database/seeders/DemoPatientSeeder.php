<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class DemoPatientSeeder extends Seeder
{
    public function run(): void
    {
        $patient = Patient::query()->updateOrCreate(
            ['normalized_phone' => '+233200000001'],
            ['first_name' => 'Demo', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-15'],
        );

        $patient->conditions()->updateOrCreate(['name' => 'Asthma'], ['is_active' => true]);
        $patient->allergies()->updateOrCreate(['name' => 'Penicillin'], ['is_active' => true]);
        $patient->medications()->updateOrCreate(
            ['name' => 'Salbutamol inhaler'],
            ['dose' => '2 puffs', 'frequency' => 'As needed', 'is_active' => true],
        );
    }
}
