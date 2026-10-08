<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The baseline is PostgreSQL-specific and intentionally irreversible.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $expectedTables = [
            'schema_migrations',
            'hospitals',
            'staff_users',
            'patients',
            'patient_conditions',
            'patient_allergies',
            'patient_medications',
            'visits',
            'assessments',
            'consent_events',
            'hospital_submissions',
            'staff_availability',
            'audit_events',
        ];

        $expectedTypes = [
            'severity_level',
            'recommended_action',
            'hospital_capability',
            'routing_mode',
            'staff_role',
            'filling_as',
            'visit_status',
            'assessment_status',
            'submission_status',
            'arrival_type',
            'consent_type',
            'staff_availability_status',
            'sex_at_birth',
            'pregnancy_status',
        ];

        $presentTables = array_values(array_filter(
            $expectedTables,
            fn (string $table): bool => Schema::hasTable($table),
        ));

        $presentTypes = DB::table('pg_type')
            ->whereIn('typname', $expectedTypes)
            ->pluck('typname')
            ->all();

        if (count($presentTables) === count($expectedTables)
            && count($presentTypes) === count($expectedTypes)) {
            return;
        }

        if ($presentTables !== [] || $presentTypes !== []) {
            throw new RuntimeException(
                'A partial MediPath schema was detected. Refusing to apply the baseline automatically.',
            );
        }

        $sqlPath = database_path('migrations/001_initial_schema.sql');
        $sql = file_get_contents($sqlPath);

        if ($sql === false) {
            throw new RuntimeException("Unable to read the MediPath schema baseline at [$sqlPath].");
        }

        $sql = preg_replace('/\A\s*begin;\s*/i', '', $sql, 1);
        $sql = preg_replace('/\s*commit;\s*\z/i', '', (string) $sql, 1);

        DB::connection()->getPdo()->exec((string) $sql);
    }

    public function down(): void
    {
        // Clinical and audit data must never be dropped through a routine rollback.
    }
};
