<?php

use App\Filament\Resources\Seminars\SeminarResource;
use App\Models\Seminar;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'manager', 'course_lecturer'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function seminarActor(string $role): User
{
    $user = User::factory()->create(['role' => str_replace('-', '_', $role), 'status' => 'active', 'is_active' => true]);
    $user->assignRole($role);

    return $user;
}

it('denies every seminar resource entry point to course lecturers', function (): void {
    $lecturer = seminarActor('course_lecturer');
    $seminar = Seminar::query()->create(['created_by' => $lecturer->id, 'title' => 'ندوة اختبار', 'status' => 'draft']);

    $this->actingAs($lecturer)->get('/admin/seminars')->assertForbidden();
    $this->actingAs($lecturer)->get('/admin/seminars/create')->assertForbidden();
    $this->actingAs($lecturer)->get('/admin/seminars/'.$seminar->id.'/edit')->assertForbidden();
    expect(SeminarResource::shouldRegisterNavigation())->toBeFalse()
        ->and(SeminarResource::canViewAny())->toBeFalse()
        ->and(SeminarResource::canCreate())->toBeFalse()
        ->and(SeminarResource::canEdit($seminar))->toBeFalse()
        ->and(SeminarResource::canDelete($seminar))->toBeFalse();
});

it('keeps seminar administration available to administrative roles', function (string $role): void {
    $actor = seminarActor($role);
    $this->actingAs($actor);
    expect(SeminarResource::shouldRegisterNavigation())->toBeTrue()
        ->and(SeminarResource::canViewAny())->toBeTrue()
        ->and(SeminarResource::canCreate())->toBeTrue();
})->with(['super-admin', 'admin', 'manager']);
