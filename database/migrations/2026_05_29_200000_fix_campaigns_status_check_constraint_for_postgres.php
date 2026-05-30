<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_status_check");

        DB::statement("
            ALTER TABLE campaigns
            ADD CONSTRAINT campaigns_status_check
            CHECK (status IN ('draft', 'scheduled', 'sending', 'sent', 'failed', 'paused', 'completed'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_status_check");

        DB::statement("
            ALTER TABLE campaigns
            ADD CONSTRAINT campaigns_status_check
            CHECK (status IN ('draft', 'scheduled', 'sent'))
        ");
    }
};
