<?php

use App\Models\Lecturer;
use App\Services\LecturerUsernameGenerator;

it('generates approved stable lecturer usernames from the linked user id', function (string $name, int $lecturerId, int $userId, string $expected): void {
    $lecturer = new Lecturer(['name' => $name, 'user_id' => $userId]);
    $lecturer->id = $lecturerId;
    $lecturer->exists = true;
    expect(app(LecturerUsernameGenerator::class)->proposal($lecturer)['proposed_username'])->toBe($expected);
})->with([
    ['ندى محمد محمود', 3, 187, 'nada187'], ['أمجد يونس يونس', 42, 42, 'amjad42'], ['مازن محمد الجلاد', 211, 211, 'mazen211'],
]);

it('uses deterministic fallback for ordinary dictionary-missing names and never creates lec usernames', function (): void {
    $lecturer = new Lecturer(['name' => 'كسين', 'user_id' => 9]);
    $lecturer->id = 9;
    $proposal = app(LecturerUsernameGenerator::class)->proposal($lecturer);
    expect($proposal['proposed_username'])->toMatch('/^[a-z]+9$/')->and($proposal['requires_manual_review'])->toBeFalse()->and($proposal['proposed_username'])->not->toContain('lec');
});
