<?php

return [
    'template_download' => 'تحميل القالب الجاهز',
    'import_with_template' => 'استيراد باستخدام القالب',
    
    'help_title' => [
        'students' => 'تعليمات استيراد الطلاب',
        'departments' => 'تعليمات استيراد الأقسام',
        'subjects' => 'تعليمات استيراد المواد',
        'halls' => 'تعليمات استيراد القاعات',
        'lecture_sessions' => 'تعليمات استيراد جلسات المحاضرات',
        'subject_students' => 'تعليمات استيراد طلاب المادة',
    ],
    
    'help_content' => [
        'students' => 'قم بملء القالب بالبيانات التالية: <strong>رقم الطالب (فريد)</strong>, <strong>الاسم</strong>, <strong>الرقم القومي (فريد)</strong>, <strong>اسم الكلية (مطابق تماماً)</strong>, <strong>اسم القسم (مطابق تماماً)</strong>. السنة (1-4), الهاتف اختياري. تجنب التكرار في رقم الطالب/القومي.',
        'departments' => '<strong>الكود (فريد)</strong>, <strong>الاسم</strong>, <strong>اسم الكلية الموجودة بالنظام</strong>. الاسم الإنجليزي اختياري. الحالة: نعم/لا/1/0/true/false.',
        'subjects' => '<strong>كود المادة (فريد)</strong>, <strong>اسم المادة</strong>, <strong>اسم المحاضر (من course_lecturer)</strong>, <strong>اسم القسم</strong>, <strong>الساعات (1-6)</strong>, المستوى (1-5), الفصل (1-2).',
        'halls' => '<strong>الكود (فريد)</strong>, <strong>الاسم</strong>, <strong>الدور</strong>, <strong>السعة</strong>, جهاز عرض (نعم/لا), حاسب (نعم/لا), اسم الشبكة, نطاق IP بداية/نهاية (صالح IP).',
        'lecture_sessions' => '<strong>اسم المادة الموجودة</strong>, <strong>اسم القاعة</strong>, <strong>التاريخ (15-10-2024)</strong>, <strong>وقت البداية (09:00)</strong>, <strong>نهاية (10:30)</strong>. الحالة: scheduled/active/completed/cancelled.',
        'subject_students' => 'للمادة الحالية: <strong>رقم الطالب</strong>, <strong>الاسم</strong>, الرقم القومي اختياري, الفصل/السنة. يُنشأ الطالب تلقائياً إن لم يوجد.',
    ],
    
    'preview_title' => 'مثال على السطور الأولى من القالب',
    'tips_title' => 'نصائح مهمة قبل الرفع',
    'tips' => [
        'match_names' => 'يجب أن تتطابق أسماء الكليات/الأقسام/المواد/القاعات <strong>بالضبط</strong> مع النظام',
        'date_format' => 'التاريخ: يوم-شهر-سنة (15-10-2024)',
        'time_format' => 'الوقت: ساعة:دقيقة (09:00) أو وقت Excel مباشر',
        'boolean_values' => 'نعم/لا/1/0/true/false للحقول المنطقية',
        'unique_fields' => 'الكود/رقم الطالب/القومي يجب أن يكون فريداً',
        'file_size' => 'حجم الملف الأقصى: 50 ميجابايت',
    ],
    
    'stats' => [
        'imported' => ':count تم استيرادهم بنجاح',
        'errors' => ':count خطأ في الاستيراد',
        'download_errors' => 'تحميل ملف الأخطاء',
    ],
    
    'import_students_template' => 'قالب طلاب المادة',
    'import_students' => 'استيراد طلاب المادة',

    'example_title' => 'مثال على ملف الإكسل المطلوب',
    'required_columns' => 'الأعمدة المطلوبة:',
    'optional_columns' => 'الأعمدة الاختيارية:',
    'exact_match' => ':field يجب أن يطابق بالضبط أسماء :type الموجودة في النظام',
    'boolean_values_note' => 'المنطقي: نعم/لا/صح/خطأ/1/0',
    'date_format_note' => 'التاريخ: يوم-شهر-سنة',
    'time_format_note' => 'الوقت: ساعة:دقيقة أو وقت Excel',
    'column_order_note' => 'ترتيب الأعمدة غير مهم',
    'extra_columns_note' => 'الأعمدة الزائدة تُتجاهل',
    'xlsx_only_note' => 'المقبول: xlsx/xls فقط',
    'modal_title' => [
        'students' => 'استيراد الطلاب من إكسل',
        'departments' => 'استيراد الأقسام من إكسل',
        'subjects' => 'استيراد المواد من إكسل',
        'halls' => 'استيراد القاعات من إكسل',
        'lecture_sessions' => 'استيراد جلسات المحاضرات من إكسل',
        'subject_students' => 'استيراد طلاب المادة من إكسل',
    ],
    'intro_text' => 'ارفع ملف Excel مطابق للمثال أو حمّل القالب.',
    'instructions_title' => 'تعليمات هامة',
    'use_headers' => 'استخدم نفس رؤوس الأعمدة المعروضة أدناه',
    'modal_download_label' => 'تحميل القالب الجاهز',
    'modal_download_sentence' => 'حمّل القالب، املأه بنفس أسماء الأعمدة، ثم ارفعه هنا.',
    'simple_instructions' => [
        'حمّل القالب واملأه باستخدام نفس أسماء الأعمدة',
        'لا تغيّر رؤوس الأعمدة',
        'يُقبل فقط ملفات xlsx/xls',
        'بعض الحقول يجب أن تطابق تماماً السجلات الموجودة في النظام'
    ],
];

