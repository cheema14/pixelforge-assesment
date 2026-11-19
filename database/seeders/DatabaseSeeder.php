<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The seeded accounts share one password so the app can be reviewed
     * without a registration step:
     *
     *  - admin@lumina.test / password (admin report access)
     *  - demo@lumina.test  / password (regular studio account)
     *
     * No generations are seeded on purpose: the admin report reconciles
     * our provider cost against Pixelforge's real usage report, and fake
     * rows would only create noise there.
     */
    public function run(CreditLedger $credits): void
    {
        $admin = User::query()->forceCreate([
            'name' => 'Lumina Admin',
            'email' => 'admin@lumina.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        $demo = User::query()->forceCreate([
            'name' => 'Demo Artist',
            'email' => 'demo@lumina.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        foreach ([$admin, $demo] as $user) {
            $credits->grantWelcomeCredit($user);
            $credits->purchase($user, 25_000_000_000, 'Starter credits (demo)');
        }
    }
}
