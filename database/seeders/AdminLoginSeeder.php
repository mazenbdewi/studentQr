<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminLoginSeeder extends Seeder
{
    public const LOGIN_USERNAME = 'admin';
    public const PIN = '123456';

    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $username = strtolower(trim((string) config('app.admin_login_username', self::LOGIN_USERNAME)));
        $user = User::withTrashed()->firstOrNew(['login_username' => $username]);
        $user->fill([
            'name' => config('app.admin_name', 'مدير النظام'),
            'pin_code' => Hash::make(self::PIN),
            'pin_enabled' => true,
            'pin_changed_at' => now(),
            'role' => 'super_admin',
            'type' => 'admin',
            'status' => 'active',
            'is_active' => true,
        ]);

        if (! $user->exists) {
            $user->password = Hash::make(config('app.admin_password', 'admin'));
        }
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles(['super-admin']);
    }
}
