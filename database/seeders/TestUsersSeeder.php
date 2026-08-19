<?php

namespace Database\Seeders;

use App\Modules\User\Domain\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = 'admin' . '@' . 'nexo' . '.com';

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'password' => 'Password1!',
                'scopes' => ['*:*'],
                'active' => true,
            ]
        );
    }
}