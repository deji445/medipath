<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consultant_requests')
            && ! Schema::hasColumn('consultant_requests', 'phone_verified_at')) {
            Schema::table('consultant_requests', function (Blueprint $table): void {
                $table->timestampTz('phone_verified_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        // Callback verification evidence is intentionally preserved.
    }
};
