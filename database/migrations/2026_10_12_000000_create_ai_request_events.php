<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_request_events')) {
            return;
        }

        Schema::create('ai_request_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('feature', 60);
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->string('outcome', 20);
            $table->unsignedInteger('duration_ms');
            $table->unsignedTinyInteger('attempt_count');
            $table->unsignedTinyInteger('retry_count');
            $table->string('error_code', 100)->nullable();
            $table->timestampTz('created_at');
            $table->index(['feature', 'created_at']);
            $table->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        // Operational history is intentionally preserved during routine rollbacks.
    }
};
