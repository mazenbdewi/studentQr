<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminLoginSeeder extends Seeder
{
    public const EMAIL = 'super@admin.com';
    public const PASSWORD = '12345678';
    public const PIN = '123456';

    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $user = User::withTrashed()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(self::PASSWORD),
                'pin_code' => Hash::make(self::PIN),
                'pin_enabled' => true,
                'pin_changed_at' => now(),
                'role' => 'super_admin',
                'type' => 'admin',
                'status' => 'active',
                'is_active' => true,
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles(['super-admin']);
    }
}
