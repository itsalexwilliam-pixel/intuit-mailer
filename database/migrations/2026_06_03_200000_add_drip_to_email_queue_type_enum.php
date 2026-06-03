<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'drip' to the email_queue.type ENUM (MySQL only).
     * SQLite stores ENUMs as TEXT and already accepts any value — no change needed.
     */
    public function up(): void
    {
        if ($this->isMysql()) {
            DB::statement("ALTER TABLE `email_queue` MODIFY COLUMN `type` ENUM('campaign', 'single', 'smtp_test', 'drip') NOT NULL DEFAULT 'campaign'");
        }
    }

    /**
     * Roll back: remove 'drip' from the ENUM (MySQL only).
     */
    public function down(): void
    {
        if ($this->isMysql()) {
            DB::statement("ALTER TABLE `email_queue` MODIFY COLUMN `type` ENUM('campaign', 'single', 'smtp_test') NOT NULL DEFAULT 'campaign'");
        }
    }

    private function isMysql(): bool
    {
        $connection = config('database.default');
        return config("database.connections.{$connection}.driver") === 'mysql';
    }
};
