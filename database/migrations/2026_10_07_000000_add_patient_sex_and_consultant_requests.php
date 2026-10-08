<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patients') && ! Schema::hasColumn('patients', 'sex_at_birth')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("alter table patients add column sex_at_birth sex_at_birth not null default 'UNKNOWN'");
            } else {
                Schema::table('patients', function (Blueprint $table): void {
                    $table->string('sex_at_birth')->default('UNKNOWN');
                });
            }
        }

        if (! Schema::hasTable('consultant_requests')) {
            Schema::create('consultant_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 160);
                $table->string('normalized_phone', 20);
                $table->text('concern')->nullable();
                $table->string('status', 20)->default('NEW');
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Patient and consultation data is intentionally preserved.
    }
};
