<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The PostgreSQL baseline is absent in SQLite's framework-only tests.
        if (Schema::getConnection()->getDriverName() === 'sqlite' && ! Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->text('profile_photo')->nullable();
        });
    }

    public function down(): void
    {
        // The PostgreSQL baseline is absent in SQLite's framework-only tests.
        if (Schema::getConnection()->getDriverName() === 'sqlite' && ! Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('profile_photo');
        });
    }
};
