<?php

namespace App\Filament\Resources\LectureSessions\RelationManagers;

use App\Exports\AttendanceExport;
use App\Exports\AttendanceWithStatusExport;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    public static function getTitle(?Model $ownerRecord = null, ?string $pageClass = null): string
    {
        return __('attendance.present_students');
    }

    public function table(Table $table): Table
    {
        // return $table
        //     ->columns([

        //         TextColumn::make('student.name')
        //             ->label(__('attendance.student_name'))
        //             ->searchable(),

        //         TextColumn::make('student.student_number')
        //             ->label(__('attendance.student_number'))
        //             ->searchable(),

        //         TextColumn::make('attendance_time')
        //             ->label(__('attendance.attendance_time'))
        //             ->dateTime(),
        //             ])

        //     ->headerActions([
        //         Action::make('export_excel')
        //             ->label(__('attendance.export_excel'))
        //             ->icon('heroicon-o-arrow-down-tray')
        //             ->action(function ($livewire) {

        //                 $records = $livewire->getRelationship()->with('student')->get();

        //                 return Excel::download(
        //                     new AttendanceExport($records),
        //                     'attendance.xlsx'
        //                 );
        //             }),

        //     ])
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->whereIn('id', function ($sub) {
                    $sub->selectRaw('MAX(id)')
                        ->from('attendances')
                        ->where('lecture_session_id', $this->ownerRecord->id)
                        ->groupBy('student_id');
                })
                ->orderBy('attendance_time', 'desc')
            )
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('attendance.student_name'))
                    ->searchable(),
                TextColumn::make('student.student_number')
                    ->label(__('attendance.student_number'))
                    ->searchable(),
                TextColumn::make('attendance_time')
                    ->label(__('attendance.attendance_time'))
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label(__('attendance.export_excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        $records = $livewire->getRelationship()
                            ->with('student')
                            ->get()
                            ->unique('student_id');

                        return Excel::download(
                            new AttendanceExport($records),
                            'attendance.xlsx'
                        );
                    }),
                Action::make('export_full_attendance')
                    ->label(__('attendance.export_full'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($livewire) {
                        $session = $livewire->getOwnerRecord();

                        return Excel::download(
                            new AttendanceWithStatusExport($session),
                            'full_attendance.xlsx'
                        );
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
