<?php

namespace App\Exports;

use App\Services\HallMetadataService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HallMetadataTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return (new ArabicArrayWorkbookExport([
            [
                'title' => HallMetadataService::WORKSHEET_HALLS,
                'headings' => HallMetadataService::headings(),
                'rows' => app(HallMetadataService::class)->templateRows(),
            ],
            [
                'title' => 'تعليمات التعبئة',
                'headings' => ['التعليمات'],
                'rows' => [
                    ['التعليمات' => 'يتم تحديث القاعات حسب رمز القاعة الموجود فقط.'],
                    ['التعليمات' => 'اترك الخلية فارغة للحفاظ على القيمة الحالية.'],
                    ['التعليمات' => 'لا يتم حذف القاعات من خلال هذا الملف.'],
                    ['التعليمات' => 'لا يتم تغيير رمز القاعة من خلال هذا الملف.'],
                    ['التعليمات' => 'أنواع القاعات المدعومة: قاعة نظرية، مخبر، ورشة، مخبر حاسوبي، مرسم، مدرج، أخرى.'],
                    ['التعليمات' => 'السعة رقم صحيح موجب.'],
                    ['التعليمات' => 'القاعة غير الفعالة لن تُقترح لإسناد المواعيد.'],
                    ['التعليمات' => 'يجب التأكد إداريًا من نوع القاعة ومبناها وكليتها.'],
                ],
            ],
        ]))->sheets();
    }
}
