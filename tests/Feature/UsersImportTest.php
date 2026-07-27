<?php

use App\Imports\UsersImport;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'manager', 'course_lecturer', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('imports a username-only user from the exported heading contract', function (): void {
    $path = usersImportWorkbookPath([
        ['name', 'login_username', 'password', 'role'],
        ['Test Admin', '  ADMIN_EXAMPLE  ', 'temporary-password', 'admin'],
    ]);

    try {
        $import = new UsersImport;
        Excel::import($import, $path);

        $user = User::query()->where('login_username', 'admin_example')->firstOrFail();

        expect($import->getImportedCount())->toBe(1)
            ->and($user->name)->toBe('Test Admin')
            ->and($user->login_username)->toBe('admin_example')
            ->and(Hash::check('temporary-password', $user->password))->toBeTrue()
            ->and($user->hasRole('admin'))->toBeTrue()
            ->and($user->getAttributes())->not->toHaveKey('email')
            ->and((new UsersImport)->rules())->toHaveKey('login_username')
            ->and((new UsersImport)->rules())->not->toHaveKey('email');
    } finally {
        @unlink($path);
    }
});

it('synchronizes every supported imported classification to its Spatie role', function (): void {
    $classifications = [
        'super_admin' => 'super-admin',
        'admin' => 'admin',
        'manager' => 'manager',
        'attendance_monitor' => 'manager',
        'course_lecturer' => 'course_lecturer',
        'student' => 'student',
    ];
    $rows = [['name', 'login_username', 'password', 'role']];

    foreach ($classifications as $classification => $spatieRole) {
        $rows[] = ["Imported {$classification}", "imported_{$classification}", 'temporary-password', $classification];
    }

    $path = usersImportWorkbookPath($rows);

    try {
        $import = new UsersImport;
        Excel::import($import, $path);

        expect($import->getImportedCount())->toBe(count($classifications));

        foreach ($classifications as $classification => $spatieRole) {
            $user = User::query()->where('login_username', "imported_{$classification}")->firstOrFail();

            expect($user->role)->toBe($classification)
                ->and($user->hasRole($spatieRole))->toBeTrue();
        }
    } finally {
        @unlink($path);
    }
});

it('rejects duplicate imported usernames without creating another user', function (): void {
    User::factory()->create(['login_username' => 'admin_example']);
    $path = usersImportWorkbookPath([
        ['name', 'login_username', 'password', 'role'],
        ['Duplicate Admin', 'admin_example', 'temporary-password', 'admin'],
    ]);

    try {
        expect(fn (): mixed => Excel::import(new UsersImport, $path))
            ->toThrow(ValidationException::class);

        expect(User::query()->where('login_username', 'admin_example')->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('rejects missing usernames and unsupported classifications before creating users', function (): void {
    $missingUsernamePath = usersImportWorkbookPath([
        ['name', 'login_username', 'password', 'role'],
        ['Missing Username', null, 'temporary-password', 'admin'],
    ]);
    $unsupportedRolePath = usersImportWorkbookPath([
        ['name', 'login_username', 'password', 'role'],
        ['Unsupported Role', 'unsupported_role', 'temporary-password', 'unsupported'],
    ]);

    try {
        expect(fn (): mixed => Excel::import(new UsersImport, $missingUsernamePath))
            ->toThrow(ValidationException::class)
            ->and(fn (): mixed => Excel::import(new UsersImport, $unsupportedRolePath))
            ->toThrow(ValidationException::class)
            ->and(User::query()->count())->toBe(0);
    } finally {
        @unlink($missingUsernamePath);
        @unlink($unsupportedRolePath);
    }
});

function usersImportWorkbookPath(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);
    $path = tempnam(sys_get_temp_dir(), 'users-import-').'.xlsx';

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
