<?php

namespace App\Filament\Resources\LectureSessions\RelationManagers;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;  
use App\Models\Student;
use App\Exports\AbsentStudentsExport;

class AbsentStudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students'; 

    public static function getTitle(?Model $ownerRecord = null, ?string $pageClass = null): string
    {
        return __('student.absent_students');
    }

    public function table(Table $table): Table
    {  $session = $this->ownerRecord;  
         
        $sessionId = $this->ownerRecord->id;
        $subjectId = $session->subject_id;

        return $table

        ->query(
            Student::whereHas('enrollments', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            })
            ->whereDoesntHave('attendances', function ($q) use ($session) {
                $q->where('lecture_session_id', $session->id);
            

            // ->query(Student::whereDoesntHave('attendances', function ($q) use ($sessionId) {
            //     $q->where('lecture_session_id', $sessionId);
            }))
            ->columns([
                TextColumn::make('name')->label(__('attendance.student_name'))->searchable(),
                TextColumn::make('student_number')->label(__('attendance.student_number'))->searchable(),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label(__('attendance.export_excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) use ($sessionId) {
                        $records = Student::whereDoesntHave('attendances', function ($q) use ($sessionId) {
                            $q->where('lecture_session_id', $sessionId);
                        })->get();

                        return Excel::download(new AbsentStudentsExport($records), 'absent_students.xlsx');
                    }),
                    // Action::make('export_pdf')
                    // ->label(__('attendance.export_pdf'))
                    // ->icon('heroicon-o-document-text')
                    // ->action(function ($livewire) use ($sessionId) {
                    //     $records = Student::whereDoesntHave('attendances', function ($q) use ($sessionId) {
                    //         $q->where('lecture_session_id', $sessionId);
                    //     })->orderBy('name')->get(); 
                
                    //     $pdf = Pdf::loadView('exports.absent_students_pdf', ['records' => $records])
                    //     ->setPaper('A4', 'portrait')
                    //     ->setOption('isHtml5ParserEnabled', true)
                    //     ->setOption('defaultFont', 'dejavusans')
                    //     ->setOption('isRemoteEnabled', true);
                
                    //     return response()->streamDownload(fn () => print($pdf->output()), 'absent_students.pdf');
                    // }),


                // Action::make('export_pdf')
                //     ->label(__('attendance.export_pdf'))
                //     ->icon('heroicon-o-document-text')
                //     ->action(function ($livewire) use ($sessionId) {
                //         $records = Student::whereDoesntHave('attendances', function ($q) use ($sessionId) {
                //             $q->where('lecture_session_id', $sessionId);
                //         })->get();

                //         $pdf = Pdf::loadView('exports.absent_students_pdf', ['records' => $records]);
                //         return response()->streamDownload(fn () => print($pdf->output()), 'absent_students.pdf');
                //     }),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}