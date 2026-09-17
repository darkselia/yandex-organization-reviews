<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $name = (string) config('auth.seed_user.name');
        $email = (string) config('auth.seed_user.email');
        $password = (string) config('auth.seed_user.password');

        if ($email === '' || $password === '') {
            throw new LogicException('SEED_USER_EMAIL and SEED_USER_PASSWORD must be configured.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );
    }
}
