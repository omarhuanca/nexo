<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = 'admin' . '@' . 'nexo' . '.com';

        $this->call([
            TestUsersSeeder::class,
        ]);

        $this->command->info('Database seeding completed.');
        $this->command->info("Admin: {$adminEmail} / Password1!");
    }
}