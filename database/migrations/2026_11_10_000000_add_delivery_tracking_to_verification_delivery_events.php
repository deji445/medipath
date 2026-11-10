<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('verification_delivery_events')) {
            return;
        }

        Schema::table('verification_delivery_events', function (Blueprint $table): void {
            $table->string('delivery_status', 20)->nullable()->after('outcome');
            $table->unsignedSmallInteger('delivery_check_count')->default(0)->after('attempt_count');
            $table->timestampTz('delivery_status_checked_at')->nullable()->after('delivery_check_count');
            $table->timestampTz('delivery_resolved_at')->nullable()->after('delivery_status_checked_at');
            $table->string('delivery_error_code', 100)->nullable()->after('error_code');
            $table->index(['delivery_status', 'created_at'], 'verification_delivery_status_created_index');
        });
    }

    public function down(): void
    {
        // Delivery audit history is intentionally preserved during routine rollbacks.
    }
};
