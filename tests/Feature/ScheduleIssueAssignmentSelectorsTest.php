<?php

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\User;
use App\Services\BlockedWeeklySlotReconciliationService;

it('searches active lecturers lazily by name and login username with a result limit', function (): void {
    $user = User::factory()->create(['login_username' => 'selector_lecturer']);
    Lecturer::query()->create(['user_id' => $user->id, 'lecturer_id' => 'LEC-SELECTOR', 'name' => 'محاضر الاختبار', 'is_active' => true]);
    Lecturer::query()->create(['lecturer_id' => 'LEC-INACTIVE', 'name' => 'محاضر غير فعال', 'is_active' => false]);

    $service = app(BlockedWeeklySlotReconciliationService::class);

    expect($service->lecturerOptions('م'))->toBe([])
        ->and($service->lecturerOptions('selector'))->toHaveCount(1)
        ->and($service->lecturerOptions('selector')[0]['label'])->toContain('محاضر الاختبار')
        ->and($service->lecturerOptions('غير فعال'))->toBe([]);
});

it('searches active halls lazily and never duplicates an identical code and name', function (): void {
    $hall = Hall::query()->create(['code' => 'B-12', 'name' => 'B-12', 'is_active' => true]);
    Hall::query()->create(['code' => 'C-01', 'name' => 'قاعة غير فعالة', 'is_active' => false]);

    $service = app(BlockedWeeklySlotReconciliationService::class);
    $options = $service->hallOptions('B-');

    expect($service->hallOptions('B'))->toBe([])
        ->and($options)->toHaveCount(1)
        ->and($options[0]['id'])->toBe($hall->id)
        ->and($options[0]['label'])->toBe('B-12')
        ->and($hall->displayLabel())->toBe('B-12');
});
