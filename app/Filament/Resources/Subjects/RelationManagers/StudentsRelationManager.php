<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('student.enrolled_students');
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student_number')
                    ->label(__('student.student_number')),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('student.name'))
                    ->url(fn (Student $record): string => StudentResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('pivot.semester')
                    ->label(__('enrollments.semester')),

                Tables\Columns\TextColumn::make('pivot.year')
                    ->label(__('enrollments.year')),

                Tables\Columns\TextColumn::make('pivot.status')
                    ->label(__('enrollments.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enrolled' => 'success',
                        'dropped' => 'danger',
                        'passed' => 'info',
                        'failed' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('add_student_manually')
                    ->label(__('enrollments.attach_student'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->modalHeading('إضافة طالب يدويًا')
                    ->form([
                        Forms\Components\TextInput::make('student_number')
                            ->label(__('student.student_number'))
                            ->required()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label(__('student.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('national_number')
                            ->label(__('student.national_number'))
                            ->maxLength(20),

                        Forms\Components\TextInput::make('semester')
                            ->label(__('enrollments.semester'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(2),

                        Forms\Components\TextInput::make('year')
                            ->label(__('enrollments.year'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(6),

                        Forms\Components\Select::make('status')
                            ->label(__('enrollments.status'))
                            ->options([
                                'enrolled' => __('enrollments.enrolled'),
                                'dropped' => __('enrollments.dropped'),
                                'passed' => __('enrollments.passed'),
                                'failed' => __('enrollments.failed'),
                            ])
                            ->default('enrolled')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $studentNumber = trim((string) $data['student_number']);
                        $name = trim((string) $data['name']);
                        $nationalNumber = isset($data['national_number']) && $data['national_number'] !== ''
                            ? trim((string) $data['national_number'])
                            : null;

                        $semester = (int) $data['semester'];
                        $year = (int) $data['year'];
                        $status = $data['status'];

                        $student = Student::firstOrCreate(
                            [
                                'student_number' => $studentNumber,
                            ],
                            [
                                'name' => $name,
                                'national_number' => $nationalNumber,
                            ]
                        );

                        // إذا كان الطالب موجودًا مسبقًا ونريد تحديث البيانات الفارغة أو القديمة
                        $needsUpdate = false;

                        if (blank($student->name) && filled($name)) {
                            $student->name = $name;
                            $needsUpdate = true;
                        }

                        if (blank($student->national_number) && filled($nationalNumber)) {
                            $student->national_number = $nationalNumber;
                            $needsUpdate = true;
                        }

                        if ($needsUpdate) {
                            $student->save();
                        }

                        $alreadyAttached = $this->ownerRecord->students()
                            ->where('students.id', $student->id)
                            ->exists();

                        if ($alreadyAttached) {
                            Notification::make()
                                ->title(__('student.already_enrolled'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $this->ownerRecord->students()->attach($student->id, [
                            'semester' => $semester,
                            'year' => $year,
                            'status' => $status,
                        ]);

                        Notification::make()
                            ->title('تمت إضافة الطالب وربطه بالمادة بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('semester')
                            ->label(__('enrollments.semester'))
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('year')
                            ->label(__('enrollments.year'))
                            ->numeric()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label(__('enrollments.status'))
                            ->options([
                                'enrolled' => __('enrollments.enrolled'),
                                'dropped' => __('enrollments.dropped'),
                                'passed' => __('enrollments.passed'),
                                'failed' => __('enrollments.failed'),
                            ])
                            ->required(),
                    ])
                    ->using(function (Model $record, array $data) {
                        $record->pivot->update([
                            'semester' => $data['semester'],
                            'year' => $data['year'],
                            'status' => $data['status'],
                        ]);

                        return $record;
                    }),

                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}