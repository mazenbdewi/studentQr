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
        $data['email'] = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $data['password'] = isset($data['password']) ? trim((string) $data['password']) : null;
        $data['role'] = isset($data['role']) ? trim((string) $data['role']) : null;

        return $data;
    }

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string', Rule::in(UserResource::getImportableRoleValues())],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => __('user.import_name_required'),
            'name.max' => __('user.import_name_max'),
            'email.required' => __('user.import_email_required'),
            'email.email' => __('user.import_email_invalid'),
            'email.unique' => __('user.import_email_unique'),
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
            'email' => __('user.email'),
            'password' => __('user.password'),
            'role' => __('user.role'),
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
