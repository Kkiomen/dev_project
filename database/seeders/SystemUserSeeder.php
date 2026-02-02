<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemUserSeeder extends Seeder
{
    /**
     * System user email for library templates.
     */
    public const SYSTEM_USER_EMAIL = 'library@system.local';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => self::SYSTEM_USER_EMAIL],
            [
                'name' => 'System Library',
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'is_admin' => false,
            ]
        );
    }

    /**
     * Get the system library user.
     */
    public static function getSystemUser(): User
    {
        return User::where('email', self::SYSTEM_USER_EMAIL)->firstOrFail();
    }
}
