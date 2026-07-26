<?php

use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\LectureSession;
use App\Models\LectureSessionGenerationRun;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function lectureSessionGenerationLoadingAdmin(): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('super-admin');

    return $admin;
}

function lectureSessionGenerationLoadingTerm(): AcademicTerm
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'فصل اختبار التحميل',
        'canonical_name' => 'generation-loading-term-'.uniqid(),
        'teaching_start_date' => now()->subDay()->toDateString(),
        'teaching_end_date' => now()->addDay()->toDateString(),
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $term->id);

    return $term;
}

function lectureSessionGenerationLoadingAction(ListLectureSessions $page): Action
{
    return collect($page->getCachedHeaderActions())
        ->first(fn (mixed $action): bool => $action instanceof Action && $action->getName() === 'generate_from_weekly_schedule');
}

it('renders an action-scoped generation loading state without generating sessions', function (): void {
    $admin = lectureSessionGenerationLoadingAdmin();
    lectureSessionGenerationLoadingTerm();
    $before = [LectureSession::query()->count(), LectureSessionGenerationRun::query()->count()];

    Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->mountAction('generate_from_weekly_schedule');

    $loadingView = file_get_contents(resource_path('views/filament/components/lecture-session-generation-loading.blade.php'));
    $renderedLoadingView = view('filament.components.lecture-session-generation-loading', ['readySessionCount' => 0])->render();

    expect($loadingView)->toContain('wire:loading')
        ->toContain('wire:target="callMountedAction"')
        ->toContain('fixed inset-0 z-[9999] flex items-center justify-center')
        ->toContain('backdrop-blur-sm')
        ->toContain('animate-spin')
        ->toContain('animate-pulse')
        ->toContain('عملية توليد الجلسات قيد التنفيذ')
        ->and($renderedLoadingView)->toContain('جارٍ توليد جلسات المحاضرات...')
        ->toContain('جارٍ تجهيز 0 جلسة.')
        ->toContain('يرجى الانتظار وعدم إغلاق الصفحة حتى انتهاء العملية.')
        ->and([LectureSession::query()->count(), LectureSessionGenerationRun::query()->count()])->toBe($before);

    expect(str_contains($loadingView, '%'))->toBeFalse();
});

it('locks the generation modal and rejects a concurrent term request before it can generate', function (): void {
    $admin = lectureSessionGenerationLoadingAdmin();
    $term = lectureSessionGenerationLoadingTerm();
    $component = Livewire::actingAs($admin)->test(ListLectureSessions::class);
    $action = lectureSessionGenerationLoadingAction($component->instance());
    $lock = Cache::lock('lecture-session-generation:'.$term->id, 600);

    expect($action->isModalClosedByClickingAway())->toBeFalse()
        ->and($action->isModalClosedByEscaping())->toBeFalse()
        ->and($action->hasModalCloseButton())->toBeFalse()
        ->and($action->getModalSubmitAction()?->getExtraAttributes())->toMatchArray([
            'wire:loading.attr' => 'disabled',
            'wire:target' => 'callMountedAction',
        ])
        ->and($action->getModalCancelAction()?->getExtraAttributes())->toMatchArray([
            'wire:loading.attr' => 'disabled',
            'wire:target' => 'callMountedAction',
        ]);

    expect($lock->get())->toBeTrue();

    try {
        $component
            ->callAction('generate_from_weekly_schedule')
            ->assertNotified('توجد عملية توليد جلسات قيد التنفيذ حاليًا. يرجى الانتظار حتى اكتمالها.');

        expect(LectureSession::query()->count())->toBe(0)
            ->and(LectureSessionGenerationRun::query()->count())->toBe(0);
    } finally {
        $lock->release();
    }
});

it('releases the term-scoped generation lock on either completion path', function (): void {
    $page = file_get_contents(app_path('Filament/Resources/LectureSessions/Pages/ListLectureSessions.php'));

    expect($page)->toContain("Cache::lock('lecture-session-generation:'.\$term->id, 600)")
        ->toContain('} catch (\\Throwable) {')
        ->toContain('} finally {')
        ->toContain('$lock->release();')
        ->toContain('تعذر إكمال توليد جلسات المحاضرات. لم يتم تنفيذ طلب آخر تلقائيًا. يرجى مراجعة سجل العملية ثم المحاولة مجددًا.');
});
