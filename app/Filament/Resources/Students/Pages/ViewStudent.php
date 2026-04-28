<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Attendance;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ViewStudent extends ViewRecord implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'students.pages.view-student';

    protected static string $resource = StudentResource::class;

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->form->fill([
            'student_number' => $this->record->student_number,
            'department_name' => $this->record->department?->name,
            'faculty_name' => $this->record->faculty?->name,
            'phone' => $this->record->phone,
        ]);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextInput::make('student_number')
                                    ->label(__('student.student_number'))
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('faculty_name')
                                    ->label(__('student.faculty_id'))
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('department_name')
                                    ->label(__('student.department_id'))
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('phone')
                                    ->label(__('student.phone'))
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAttendanceHistoryQuery())
            ->columns([
                Tables\Columns\TextColumn::make('lectureSession.subject.name')
                    ->label(__('attendance.subject'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.session_date')
                    ->label(__('attendance.attendance_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('attendance.recorded_at'))
                    ->dateTime('H:i')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('attendances.created_at', $direction)
                        ->orderBy('attendances.id', $direction)),
                Tables\Columns\TextColumn::make('lectureSession.hall.name')
                    ->label(__('attendance.hall'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.lecturer.name')
                    ->label(__('attendance.lecturer'))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('attendance_status')
                    ->label(__('attendance.status'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __("attendance.status_{$state}") : '')
                    ->colors([
                        'success' => 'present',
                        'warning' => 'late',
                        'danger' => 'absent',
                        'info' => 'excused',
                        'gray' => 'pending',
                    ]),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->label(__('attendance.subject'))
                    ->relationship('lectureSession.subject', 'name'),
                Tables\Filters\Filter::make('date_range')
                    ->label(__('attendance.date_range'))
                    ->form([
                       DatePicker::make('date_from')
                           ->label(__('attendance.date_from')),
                       DatePicker::make('date_to')
                           ->label(__('attendance.date_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $q): Builder => $q->whereDate('lecture_sessions.session_date', '>=', $data['date_from']),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $q): Builder => $q->whereDate('lecture_sessions.session_date', '<=', $data['date_to']),
                            );
                    }),
                SelectFilter::make('attendance_status')
                    ->label(__('attendance.status'))
                    ->options([
                        'present' => __('attendance.status_present'),
                        'absent' => __('attendance.status_absent'),
                        'late' => __('attendance.status_late'),
                        'excused' => __('attendance.status_excused'),
                        'pending' => __('attendance.status_pending'),
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading(__('student.no_attendance_history'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('attendances.created_at', 'desc')
                ->orderBy('attendances.id', 'desc'))
            ->headerActions([
                ExportBulkAction::make()
                    ->label(__('attendance.export_excel')),
            ]);
    }

    protected function getAttendanceHistoryQuery(): Builder
    {
        return Attendance::query()
            ->select('attendances.*')
            ->with(['lectureSession' => fn (Relation $query) => $query->with(['subject', 'lecturer', 'hall'])])
            ->where('attendances.student_id', $this->record->id)
            ->leftJoin('lecture_sessions', 'attendances.lecture_session_id', '=', 'lecture_sessions.id');
    }

    public function getRelations(): array
    {
        return [
            SubjectsRelationManager::class,
        ];
    }
}
