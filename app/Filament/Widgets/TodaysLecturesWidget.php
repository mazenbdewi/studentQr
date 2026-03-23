<?php

namespace App\Filament\Widgets;

use App\Models\LectureSession;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Table;
use Carbon\Carbon;

class TodaysLecturesWidget extends BaseWidget implements HasTable
{
    use InteractsWithTable;

    protected int | string | array $columnSpan = 'full';


public function getHeading(): ?string
{
    $today = Carbon::today()
        ->locale(app()->getLocale());

    return __('lecture-session.todays_lectures') . ' (' .
        $today->translatedFormat('l') . ' في ' .
        $today->translatedFormat('j-n-Y') . ')';
}

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('lecture-session.todays_lectures'))
            ->query(
                LectureSession::query()
                    ->with(['lecturer', 'subject'])
                    ->whereDate('session_date', today())
                    ->orderBy('start_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('lecture-session.singular'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('session_date')
                    ->label(__('lecture-session.session_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label(__('lecture-session.start_time'))
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label(__('lecture-session.end_time'))
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lecturer.name')
                    ->label(__('lecture-session.lecturer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label(__('lecture-session.subject'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('lecture-session.status'))
                    ->colors([
                        'warning' => 'scheduled',
                        'success' => 'active',
                        'secondary' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => __("lecture-session.status_{$state}")),
            ])
            ->defaultSort('start_time', 'asc')
            ->emptyStateHeading(__('lecture-session.no_lectures_today'))
            ->paginated(false)
            ->poll('60s');
    }
}