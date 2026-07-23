<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = '123';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super-admin', 'admin', 'manager', 'course_lecturer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $reconciliationPermissions = collect([
            'view schedule-import reconciliation',
            'resolve schedule-import subject mapping',
            'resolve schedule-import section mapping',
            'assign schedule-import weekly time',
            'assign schedule-import lecturer',
            'create schedule-import lecturer identity',
            'assign schedule-import hall',
            'create schedule-import hall',
            'resolve schedule-import conflict',
            'ignore schedule-import issues',
            'retry schedule-import rows',
            'export schedule-import reconciliation',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($reconciliationPermissions);

        $weeklySchedulePermissions = collect([
            'view weekly schedule',
            'view weekly schedule reports',
            'export weekly schedule reports',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($weeklySchedulePermissions);
        Role::findByName('super-admin', 'web')->givePermissionTo($weeklySchedulePermissions);

        $lecturerAccountPreparationPermissions = collect([
            'view lecturer-account preparation',
            'manage lecturer-account preparation',
            'prepare lecturer login accounts',
            'link lecturer user accounts',
            'preview bulk lecturer account preparation',
            'generate lecturer login accounts',
            'view lecturer account generation runs',
            'download lecturer temporary credentials',
            'reset bulk lecturer temporary passwords',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($lecturerAccountPreparationPermissions);
        Role::findByName('super-admin', 'web')->givePermissionTo($lecturerAccountPreparationPermissions);

        $lectureSessionGenerationPermissions = collect([
            'preview lecture-session weekly generation',
            'generate lecture-session weekly schedule',
            'preview lecture-session generation',
            'generate lecture sessions from weekly schedule',
            'view lecture-session generation runs',
            'export lecture-session generation reports',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($lectureSessionGenerationPermissions);
        Role::findByName('super-admin', 'web')->givePermissionTo($lectureSessionGenerationPermissions);

        $blockedWeeklySlotReconciliationPermissions = collect([
            'preview blocked weekly slot reconciliation',
            'reconcile blocked weekly slots',
            'create lecturer identity from source',
            'change reconciled lecturer',
            'change reconciled hall',
            'change reconciled weekly time',
            'exclude weekly slot from current batch',
            'view reconciliation audit history',
            'export blocked weekly slot reports',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($blockedWeeklySlotReconciliationPermissions);
        Role::findByName('super-admin', 'web')->givePermissionTo($blockedWeeklySlotReconciliationPermissions);

        $hallMetadataPermissions = collect([
            'manage hall metadata',
            'export hall metadata',
            'import hall metadata',
            'preview hall metadata import',
            'preview grouped hall assignment',
            'confirm grouped hall assignment with warning',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($hallMetadataPermissions);
        Role::findByName('super-admin', 'web')->givePermissionTo($hallMetadataPermissions);

        $manualLectureSessionPermissions = collect([
            'create manual lecture sessions',
            'override lecture session teaching period',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::findByName('admin', 'web')->givePermissionTo($manualLectureSessionPermissions->where('name', 'create manual lecture sessions'));
        Role::findByName('super-admin', 'web')->givePermissionTo($manualLectureSessionPermissions);

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
