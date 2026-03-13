<?php

namespace App\Filament\Resources\StudentDevices;

use App\Filament\Resources\StudentDevices\Pages\CreateStudentDevice;
use App\Filament\Resources\StudentDevices\Pages\EditStudentDevice;
use App\Filament\Resources\StudentDevices\Pages\ListStudentDevices;
use App\Filament\Resources\StudentDevices\Pages\ViewStudentDevice;
use App\Filament\Resources\StudentDevices\Schemas\StudentDeviceForm;
use App\Filament\Resources\StudentDevices\Schemas\StudentDeviceInfolist;
use App\Filament\Resources\StudentDevices\Tables\StudentDevicesTable;
use App\Models\StudentDevice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentDeviceResource extends Resource
{
    protected static ?string $model = StudentDevice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'StudentDevice';

    public static function form(Schema $schema): Schema
    {
        return StudentDeviceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentDeviceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentDevicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentDevices::route('/'),
            'create' => CreateStudentDevice::route('/create'),
            'view' => ViewStudentDevice::route('/{record}'),
            'edit' => EditStudentDevice::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super-admin');
    }
}
