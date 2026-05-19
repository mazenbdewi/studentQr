<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = '123';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super-admin', 'admin', 'manager', 'course_lecturer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->createUser(
            email: 'super@admin.com',
            role: 'super-admin',
            attributes: [
                'name' => 'Super Admin',
                'role' => 'super_admin',
                'type' => 'admin',
            ],
        );

        $this->createUser(
            email: 'admin@uni.edu',
            role: 'admin',
            attributes: [
                'name' => 'Admin',
                'role' => 'admin',
                'type' => 'admin',
            ],
        );

        $this->createUser(
            email: 'ahmed@uni.edu',
            role: 'course_lecturer',
            attributes: [
                'name' => 'Dr. Ahmed',
                'role' => 'course_lecturer',
                'type' => 'lecturer',
                'title' => 'professor',
            ],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createUser(string $email, string $role, array $attributes): void
    {
        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $isNewUser = ! $user->exists;

        $user->fill([
            ...$attributes,
            'status' => 'active',
            'is_active' => true,
        ]);

        if ($isNewUser) {
            $user->password = Hash::make(self::DEFAULT_PASSWORD);
        }

        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles([$role]);
    }
}
