<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verification_delivery_events')) {
            return;
        }

        Schema::create('verification_delivery_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('provider', 40);
            $table->string('purpose', 60);
            $table->string('outcome', 20);
            $table->string('provider_reference', 100)->nullable();
            $table->string('provider_code', 40)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->unsignedTinyInteger('attempt_count');
            $table->string('error_code', 100)->nullable();
            $table->timestampTz('created_at');
            $table->index(['provider', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        // Delivery history is intentionally preserved during routine rollbacks.
    }
};
