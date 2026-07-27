<?php

use App\Filament\Pages\AcademicTermManagement;
use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Models\AppSetting;
use App\Models\LectureSession;
use App\Models\User;
use App\Support\AcademicTermContext;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function missingCurrentTermLectureSessionAdmin(): User
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Role::findOrCreate('super-admin', 'web');

    $admin = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('super-admin');

    return $admin;
}

it('keeps required-term operations strict while rendering lecture-session actions safely without a current term', function (): void {
    AppSetting::query()
        ->where('key', AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY)
        ->delete();
    $admin = missingCurrentTermLectureSessionAdmin();
    $beforeSessions = LectureSession::query()->count();
    $context = app(AcademicTermContext::class);

    expect($context->currentOrNull())->toBeNull()
        ->and(fn () => $context->requireCurrent())
        ->toThrow(RuntimeException::class, 'لا يوجد فصل دراسي حالي محدد');

    $component = Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->assertSee('توليد الجلسات من البرنامج الأسبوعي')
        ->assertSet('mountedActions.0.name', 'missingAcademicTerm');

    $headerActions = $component->instance()->getCachedHeaderActions();
    $generation = collect($headerActions)
        ->first(fn (mixed $action): bool => $action instanceof Action && $action->getName() === 'generate_from_weekly_schedule');
    $settings = collect($headerActions)
        ->first(fn (mixed $action): bool => $action instanceof ActionGroup && $action->getLabel() === 'الإعدادات');
    $missingTermAction = $component->instance()->missingAcademicTermAction();

    expect($generation)->toBeInstanceOf(Action::class)
        ->and($generation->isDisabled())->toBeFalse()
        ->and($settings)->toBeNull()
        ->and((string) $missingTermAction->getModalHeading())->toBe('لم يتم تحديد الفصل الدراسي الحالي')
        ->and((string) $missingTermAction->getModalDescription())->toContain('يجب تحديد الفصل الدراسي الحالي قبل استخدام عمليات الجلسات والمحاضرات.')
        ->and((string) $missingTermAction->getModalIcon())->toBe('heroicon-o-exclamation-triangle')
        ->and($missingTermAction->getModalIconColor())->toBe('warning')
        ->and($missingTermAction->getModalWidth())->toBe('lg')
        ->and($missingTermAction->isModalClosedByClickingAway())->toBeFalse()
        ->and($missingTermAction->isModalClosedByEscaping())->toBeFalse()
        ->and($missingTermAction->hasModalCloseButton())->toBeTrue()
        ->and((string) $missingTermAction->getModalSubmitAction()?->getLabel())->toBe('تحديد الفصل الدراسي الحالي')
        ->and($missingTermAction->getModalSubmitAction()?->getUrl())->toBe(AcademicTermManagement::getUrl())
        ->and((string) $missingTermAction->getModalCancelAction()?->getLabel())->toBe('إغلاق')
        ->and(LectureSession::query()->count())->toBe($beforeSessions);

    $component
        ->call('unmountAction')
        ->mountAction('generate_from_weekly_schedule')
        ->assertSet('mountedActions.0.name', 'missingAcademicTerm');

});
