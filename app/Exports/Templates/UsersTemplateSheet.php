<?php

namespace App\Exports\Templates;

use App\Filament\Resources\Users\UserResource;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsersTemplateSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        return collect([
            [
                __('user.import_example_name'),
                'admin@example.com',
                'ChangeMe123',
                UserResource::getImportableRoleValues()[0] ?? 'super_admin',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'email',
            'password',
            'role',
        ];
    }

    public function title(): string
    {
        return 'Users_Template';
    }
}
