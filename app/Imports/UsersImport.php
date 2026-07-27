<?php

namespace App\Imports;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

class UsersImport implements OnEachRow, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    public function prepareForValidation($data, $index): array
    {
        $data['name'] = isset($data['name']) ? trim((string) $data['name']) : null;
        $data['login_username'] = isset($data['login_username']) ? strtolower(trim((string) $data['login_username'])) : null;
        $data['password'] = isset($data['password']) ? trim((string) $data['password']) : null;
        $data['role'] = isset($data['role']) ? trim((string) $data['role']) : null;

        return $data;
    }

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        $user = User::create([
            'name' => $data['name'],
            'login_username' => $data['login_username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'type' => in_array($data['role'], ['super_admin', 'admin'], true) ? 'admin' : 'lecturer',
            'status' => 'active',
        ]);

        $user->syncSystemRole($user->role);

        $this->importedCount++;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'login_username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/', 'unique:users,login_username'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string', Rule::in(UserResource::getImportableRoleValues())],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => __('user.import_name_required'),
            'name.max' => __('user.import_name_max'),
            'login_username.required' => 'اسم المستخدم مطلوب.',
            'login_username.unique' => 'اسم المستخدم مستخدم بالفعل.',
            'password.required' => __('user.import_password_required'),
            'role.required' => __('user.import_role_required'),
            'role.in' => __('user.import_role_invalid', [
                'roles' => implode(', ', UserResource::getImportableRoleValues()),
            ]),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => __('user.name'),
            'login_username' => 'اسم المستخدم',
            'password' => __('user.password'),
            'role' => __('user.role'),
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
