<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\PinLoginService;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;

class PortalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'qr-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected string $view = 'filament.pages.portal-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return $user->email === 'super@admin.com'
            || $user->role === 'super_admin'
            || $user->hasRole('super-admin')
            || $user->hasRole('super_admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.qr_settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public function getTitle(): string
    {
        return __('settings.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'qr_base_url' => AppSetting::value('qr_base_url'),
            'enable_pin_login' => AppSetting::boolean(PinLoginService::SETTING_KEY),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.qr_section_title'))
                    ->description(__('settings.qr_section_description'))
                    ->schema([
                        TextInput::make('qr_base_url')
                            ->label(__('settings.qr_base_url'))
                            ->helperText(__('settings.qr_base_url_help', ['app_url' => config('app.url')]))
                            ->placeholder((string) config('app.url'))
                            ->url()
                            ->nullable()
                            ->maxLength(2048),
                    ]),
                Section::make(__('settings.security_section_title'))
                    ->description(__('settings.security_section_description'))
                    ->schema([
                        Toggle::make('enable_pin_login')
                            ->label(__('settings.enable_pin_login'))
                            ->helperText(__('settings.enable_pin_login_help'))
                            ->inline(false),
                        Placeholder::make('pin_login_current_status')
                            ->label(__('settings.pin_login_current_status'))
                            ->content(fn (): string => AppSetting::boolean(PinLoginService::SETTING_KEY)
                                ? __('settings.pin_login_enabled')
                                : __('settings.pin_login_disabled'))
                            ->badge()
                            ->color(fn (): string => AppSetting::boolean(PinLoginService::SETTING_KEY) ? 'success' : 'danger'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $oldQrBaseUrl = AppSetting::value('qr_base_url');
        $oldPinLoginEnabled = AppSetting::boolean(PinLoginService::SETTING_KEY);
        $qrBaseUrl = filled($data['qr_base_url'] ?? null)
            ? rtrim((string) $data['qr_base_url'], '/')
            : null;
        $pinLoginEnabled = (bool) ($data['enable_pin_login'] ?? false);

        AppSetting::put('qr_base_url', $qrBaseUrl);
        AppSetting::putBoolean(PinLoginService::SETTING_KEY, $pinLoginEnabled);

        app(ActivityLogger::class)->logSettingsChange(
            [
                'qr_base_url' => $oldQrBaseUrl,
                'enable_pin_login' => $oldPinLoginEnabled,
            ],
            [
                'qr_base_url' => $qrBaseUrl,
                'enable_pin_login' => $pinLoginEnabled,
            ],
            'portal_settings_saved'
        );

        Notification::make()
            ->title(__('settings.saved'))
            ->success()
            ->send();
    }
}
