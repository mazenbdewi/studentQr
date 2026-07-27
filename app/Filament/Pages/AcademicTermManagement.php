<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Services\AcademicTermActivationService;
use App\Support\AcademicTermContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AcademicTermManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'current-academic-term';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected string $view = 'filament.pages.academic-term-management';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('manageAcademicTerms');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.initial_setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.navigation.current_academic_term');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getTitle(): string
    {
        return 'إدارة الفصل الدراسي الحالي';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AcademicTerm::query()->orderByDesc('id'))
            ->columns([
                TextColumn::make('display_name')
                    ->label('اسم الفصل الدراسي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teaching_start_date')
                    ->label('تاريخ البداية')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('teaching_end_date')
                    ->label('تاريخ النهاية')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('current_term')
                    ->label('الفصل الحالي')
                    ->state(fn (AcademicTerm $record): string => $this->isCurrent($record) ? 'مفعّل' : 'غير مفعّل')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'مفعّل' ? 'success' : 'gray'),
            ])
            ->actions([
                Action::make('toggle_current_term')
                    ->label(fn (AcademicTerm $record): string => $this->isCurrent($record) ? 'إلغاء تحديد الفصل' : 'تحديد كفصل حالي')
                    ->icon(fn (AcademicTerm $record): string => $this->isCurrent($record)
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (AcademicTerm $record): string => $this->isCurrent($record) ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (AcademicTerm $record): string => $this->isCurrent($record)
                        ? 'إلغاء تحديد الفصل الدراسي الحالي'
                        : 'تحديد الفصل الدراسي الحالي')
                    ->modalDescription(fn (AcademicTerm $record): string => $this->isCurrent($record)
                        ? 'سيؤدي ذلك إلى إيقاف العمليات التي تتطلب وجود فصل دراسي حالي.'
                        : 'سيتم اعتماد هذا الفصل كمصدر بيانات التشغيل اليومي.')
                    ->modalSubmitActionLabel(fn (AcademicTerm $record): string => $this->isCurrent($record)
                        ? 'إلغاء تحديد الفصل'
                        : 'تحديد الفصل الدراسي الحالي')
                    ->modalCancelActionLabel('تراجع')
                    ->action(function (AcademicTerm $record): void {
                        $service = app(AcademicTermActivationService::class);

                        if ($this->isCurrent($record)) {
                            $service->deactivate(Filament::auth()->user());

                            Notification::make()
                                ->title('تم إلغاء تحديد الفصل الدراسي الحالي')
                                ->success()
                                ->send();
                        } else {
                            $service->activate($record, Filament::auth()->user());

                            Notification::make()
                                ->title('تم تحديد الفصل الدراسي الحالي')
                                ->body('تم تعيين «'.$record->display_name.'» فصلًا دراسيًا حاليًا بنجاح.')
                                ->success()
                                ->send();
                        }

                        $this->resetTable();
                    }),
                Action::make('edit_teaching_dates')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('تعديل تواريخ الفصل الدراسي')
                    ->modalSubmitActionLabel('حفظ التواريخ')
                    ->fillForm(fn (AcademicTerm $record): array => [
                        'teaching_start_date' => $record->teaching_start_date?->toDateString(),
                        'teaching_end_date' => $record->teaching_end_date?->toDateString(),
                    ])
                    ->schema([
                        DatePicker::make('teaching_start_date')
                            ->label('تاريخ البداية')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('teaching_end_date')
                            ->label('تاريخ النهاية')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('teaching_start_date'),
                    ])
                    ->action(function (AcademicTerm $record, array $data): void {
                        $record->update([
                            'teaching_start_date' => $data['teaching_start_date'] ?? null,
                            'teaching_end_date' => $data['teaching_end_date'] ?? null,
                        ]);

                        Notification::make()
                            ->title('تم حفظ تواريخ الفصل الدراسي')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ]);
    }

    private function isCurrent(AcademicTerm $term): bool
    {
        return app(AcademicTermContext::class)->isCurrent($term);
    }
}
