<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'smtp_test' to the email_queue.type ENUM.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `email_queue` MODIFY COLUMN `type` ENUM('campaign', 'single', 'smtp_test') NOT NULL DEFAULT 'campaign'");
    }

    /**
     * Roll back: remove 'smtp_test' from the ENUM.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `email_queue` MODIFY COLUMN `type` ENUM('campaign', 'single') NOT NULL DEFAULT 'campaign'");
    }
};
