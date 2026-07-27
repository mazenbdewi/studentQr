<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Services\UserGuidePdfService;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function createUserGuideSuperAdmin(): User
{
    $user = User::factory()->create([
        'login_username' => 'guide-super-admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('super-admin');

    return $user;
}

function createUserGuideAdmin(): User
{
    $user = User::factory()->create([
        'login_username' => 'guide-admin',
        'role' => 'admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('admin');

    return $user;
}

it('shows the user guide page in the admin panel for authenticated admins', function (): void {
    $user = createUserGuideAdmin();

    $this->actingAs($user)
        ->get('/admin/user-guide')
        ->assertOk()
        ->assertSee(__('user-guide.page.title'))
        ->assertSee(__('user-guide.page.download_button'));
});

it('allows admin and super admin users to download the Arabic user guide pdf', function (): void {
    foreach ([createUserGuideAdmin(), createUserGuideSuperAdmin()] as $user) {
        $response = $this->actingAs($user)->get(route('admin.user-guide.download'));

        expect($response->headers->get('content-type'))->toBe('application/pdf')
            ->and($response->headers->get('content-disposition'))->toContain('user-guide-ar.pdf');

        ob_start();
        $response->sendContent();
        $pdf = ob_get_clean();

        expect($pdf)->toStartWith('%PDF');
    }
});

it('blocks unauthenticated visitors from downloading the user guide', function (): void {
    $this->get(route('admin.user-guide.download'))
        ->assertRedirect(route('login'));
});

it('renders the user guide pdf template in rtl and uses branding from settings when available', function (): void {
    $logoPath = public_path('images/logo.png');

    expect($logoPath)->toBeFile();

    AppSetting::put('university_name', 'جامعة الاختبار');
    AppSetting::put('system_name', 'نظام الاختبار');
    AppSetting::put('logo_path', 'images/logo.png');

    $branding = app(UserGuidePdfService::class)->branding();
    $sections = trans('user-guide.pdf.sections', [], 'ar');

    $html = view('exports.user-guide-pdf', [
        'generatedAt' => now()->locale('ar'),
        'branding' => $branding,
        'sections' => $sections,
    ])->render();

    expect($html)->toContain('dir="rtl"')
        ->toContain('جامعة الاختبار')
        ->toContain('نظام الاختبار')
        ->toContain('data:image/png;base64,')
        ->toContain('المشاكل الشائعة وحلولها');
});
