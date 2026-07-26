<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Services\AcademicTermActivationService;
use App\Support\AcademicTermContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AcademicTermManagement extends Page
{
    protected static ?string $slug = 'current-academic-term';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected string $view = 'filament.pages.academic-term-management';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('manageAcademicTerms');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return 'إدارة الفصل الدراسي الحالي';
    }

    public function getTitle(): string
    {
        return 'إدارة الفصل الدراسي النشط';
    }

    protected function getHeaderActions(): array
    {
        return [$this->activateAction()];
    }

    public function activateAction(): Action
    {
        return Action::make('activate')
            ->label('تعيين  الفصل الدراسي النشط')
            ->requiresConfirmation()
            ->schema([
                Select::make('term')
                    ->label('الفصل الدراسي')
                    ->options(fn (): array => AcademicTerm::query()->orderByDesc('id')->pluck('display_name', 'id')->all())
                    ->required(),
            ])
            ->modalHeading('تعيين هذا الفصل كفصل دراسي نشط ')
            ->modalDescription('سيتم إخفاء بيانات الفصل الحالي من صفحات التشغيل اليومية، لكنها ستبقى محفوظة في الأرشيف ولن يتم حذف حسابات المستخدمين أو تغيير كلمات مرورهم.')
            ->action(function (array $data, array $arguments): void {
                $term = AcademicTerm::query()->findOrFail($arguments['term'] ?? $data['term'] ?? null);
                app(AcademicTermActivationService::class)->activate($term, Filament::auth()->user());
                Notification::make()->success()->title('تم تعيين الفصل الدراسي الحالي: '.$term->display_name)->send();
            });
    }

    public function terms(): \Illuminate\Support\Collection
    {
        return AcademicTerm::query()->orderByDesc('id')->get();
    }

    public function currentTerm(): ?AcademicTerm
    {
        return app(AcademicTermContext::class)->current();
    }
}
