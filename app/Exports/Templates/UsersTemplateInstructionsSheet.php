<?php

namespace App\Exports\Templates;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsersTemplateInstructionsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        $acceptedRoles = collect(UserResource::getImportableRoles());
        $roleDetails = $acceptedRoles
            ->map(
                fn (string $label, string $databaseRole): string => sprintf(
                    '%s (%s) -> %s',
                    $databaseRole,
                    $label,
                    User::mapDatabaseRoleToSpatieRole($databaseRole)
                )
            )
            ->implode(' | ');

        return collect([
            [__('user.import_instruction_required_columns'), 'name, email, password, role'],
            [__('user.import_instruction_name'), __('user.import_name_help')],
            [__('user.import_instruction_email'), __('user.import_email_help')],
            [__('user.import_instruction_password'), __('user.import_password_help_text')],
            [__('user.import_instruction_role'), __('user.import_role_help_text')],
            [__('user.accepted_roles'), $acceptedRoles->keys()->implode(', ')],
            [__('user.import_instruction_role_mapping'), $roleDetails],
        ]);
    }

    public function headings(): array
    {
        return [
            __('user.import_instruction_label'),
            __('user.import_instruction_details'),
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }
}
