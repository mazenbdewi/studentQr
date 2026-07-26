<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\ImportBatch;
use App\Models\LecturerCredentialBatch;
use App\Models\LectureSession;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AcademicTermArchive extends Page
{
    protected static ?string $slug = 'academic-term-archive';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    protected string $view = 'filament.pages.academic-term-archive';

    public ?int $termId = null;

    public function mount(): void
    {
        $this->termId = AcademicTerm::query()->archivedTerms()->value('id');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return 'الفصول الدراسية السابقة';
    }

    public function getTitle(): string
    {
        return 'الفصول الدراسية السابقة';
    }

    public function terms(): array
    {
        return AcademicTerm::query()->archivedTerms()->orderByDesc('id')->pluck('display_name', 'id')->all();
    }

    public function selectedTerm(): ?AcademicTerm
    {
        return $this->termId ? AcademicTerm::query()->archivedTerms()->find($this->termId) : null;
    }

    public function counts(): array
    {
        $id = $this->selectedTerm()?->id;
        if (! $id) {
            return [];
        }

        return [
            'التسجيلات' => Enrollment::query()->forAcademicTerm($id)->count(),
            'الشعب' => SubjectSection::query()->forAcademicTerm($id)->count(),
            'الجداول الأسبوعية' => SubjectSectionScheduleSlot::query()->forAcademicTerm($id)->count(),
            'الجلسات' => LectureSession::query()->forAcademicTerm($id)->count(),
            'الحضور' => Attendance::query()->whereHas('lectureSession', fn ($q) => $q->where('academic_term_id', $id))->count(),
            'دفعات الاستيراد' => ImportBatch::query()->whereHas('academicTerms', fn ($q) => $q->whereKey($id))->count(),
            'دفعات بيانات الاعتماد' => LecturerCredentialBatch::query()->where('academic_term_id', $id)->count(),
        ];
    }
}
