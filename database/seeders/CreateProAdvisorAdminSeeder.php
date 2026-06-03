<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CreateProAdvisorAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $account = Account::query()->first();

        if (! $account) {
            $account = Account::query()->create([
                'name' => 'Default Account',
                'slug' => 'default-account-' . Str::lower(Str::random(6)),
                'status' => 'active',
            ]);
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@proadvisorsupport.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'account_id' => $account->id,
            ]
        );
    }
}
