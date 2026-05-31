<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old global unique constraints/indexes on email if present.
        if (Schema::hasTable('contacts')) {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS contacts_email_unique');
                DB::statement('DROP INDEX IF EXISTS contacts_email_index');
            } elseif ($driver === 'sqlite') {
                // SQLite in tests does not support dropping indexes reliably in this flow.
                // Create the composite unique index and keep legacy indexes if present.
            } else {
                Schema::table('contacts', function (Blueprint $table) {
                    try {
                        $table->dropUnique('contacts_email_unique');
                    } catch (\Throwable $e) {
                        // Ignore if not present.
                    }

                    try {
                        $table->dropIndex('contacts_email_index');
                    } catch (\Throwable $e) {
                        // Ignore if not present.
                    }
                });
            }

            Schema::table('contacts', function (Blueprint $table) {
                try {
                    $table->unique(['account_id', 'email'], 'contacts_account_id_email_unique');
                } catch (\Throwable $e) {
                    // Ignore if already exists.
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            $driver = DB::getDriverName();

            Schema::table('contacts', function (Blueprint $table) {
                try {
                    $table->dropUnique('contacts_account_id_email_unique');
                } catch (\Throwable $e) {
                    // Ignore if not present.
                }
            });

            if ($driver !== 'sqlite') {
                Schema::table('contacts', function (Blueprint $table) {
                    try {
                        $table->unique('email', 'contacts_email_unique');
                    } catch (\Throwable $e) {
                        // Ignore if exists.
                    }

                    try {
                        $table->index('email', 'contacts_email_index');
                    } catch (\Throwable $e) {
                        // Ignore if exists.
                    }
                });
            }
        }
    }
};
