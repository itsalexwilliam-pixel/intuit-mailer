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
        $tables = [
            'users',
            'campaigns',
            'contacts',
            'groups',
            'smtp_servers',
            'email_queue',
            'unsubscribes',
            'app_settings',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'account_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('account_id')->nullable()->after('id')->constrained('accounts')->nullOnDelete();
                });
            }
        }

        $freePlanId = DB::table('plans')->where('slug', 'free')->value('id');

        $defaultAccountId = DB::table('accounts')->insertGetId([
            'name' => 'Default Account',
            'plan_id' => $freePlanId,
            'owner_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($tables as $tableName) {
            DB::table($tableName)
                ->whereNull('account_id')
                ->update(['account_id' => $defaultAccountId]);
        }

        // Preserve cross-account duplicate email capability by dropping legacy global email uniqueness
        // after account_id has been populated for all existing contacts.
        if (Schema::hasTable('contacts')) {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_email_unique');
                DB::statement('DROP INDEX IF EXISTS contacts_email_unique');
                DB::statement('DROP INDEX IF EXISTS contacts_email_index');
            } elseif ($driver === 'sqlite') {
                // SQLite keeps old indexes from initial table creation in tests.
                // Rebuild contacts table without legacy global unique(email) while preserving data.
                DB::statement('CREATE TABLE contacts_tmp (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR NOT NULL,
                    email VARCHAR NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME,
                    account_id INTEGER
                )');

                DB::statement('INSERT INTO contacts_tmp (id, name, email, created_at, updated_at, account_id)
                    SELECT id, name, email, created_at, updated_at, account_id FROM contacts');

                DB::statement('DROP TABLE contacts');
                DB::statement('ALTER TABLE contacts_tmp RENAME TO contacts');
                DB::statement('CREATE INDEX contacts_email_index ON contacts (email)');
                DB::statement('CREATE INDEX contacts_account_id_index ON contacts (account_id)');
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
        }

        $owner = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if (! $owner) {
            $owner = DB::table('users')->orderBy('id')->first();
        }

        if ($owner) {
            DB::table('accounts')
                ->where('id', $defaultAccountId)
                ->update(['owner_user_id' => $owner->id]);

            DB::table('account_user')->updateOrInsert(
                [
                    'account_id' => $defaultAccountId,
                    'user_id' => $owner->id,
                ],
                [
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $users = DB::table('users')->select('id', 'role')->get();
        foreach ($users as $user) {
            $mappedRole = match ($user->role) {
                'admin' => 'admin',
                default => 'member',
            };

            if ($owner && $user->id === $owner->id) {
                $mappedRole = 'owner';
            }

            DB::table('account_user')->updateOrInsert(
                [
                    'account_id' => $defaultAccountId,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $mappedRole,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'campaigns',
            'contacts',
            'groups',
            'smtp_servers',
            'email_queue',
            'unsubscribes',
            'app_settings',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
