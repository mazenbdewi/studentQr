<?php

return [
    'title' => 'تجهيز حسابات المدرسين',
    'saved' => 'تم حفظ تجهيز حساب المدرس',
    'fields' => [
        'lecturer_name' => 'اسم المدرس بالعربية',
        'linked_account' => 'الحساب المرتبط',
        'email' => 'البريد الإلكتروني',
        'account_status' => 'حالة الحساب',
        'course_lecturer_role_status' => 'حالة دور مدرس مقرر',
        'weekly_slots_count' => 'عدد المواعيد الأسبوعية',
        'readiness_status' => 'حالة الجاهزية لتوليد الجلسات',
        'password' => 'كلمة المرور المؤقتة',
        'password_confirmation' => 'تأكيد كلمة المرور المؤقتة',
    ],
    'actions' => [
        'create_account' => 'إنشاء حساب دخول للمدرس',
        'link_existing_account' => 'ربط المدرس بحساب موجود',
        'grant_course_lecturer_role' => 'منح صلاحية مدرس مقرر',
    ],
    'statuses' => [
        'missing_account' => 'لا يوجد حساب مرتبط',
        'broken_link' => 'رابط حساب غير صالح',
        'duplicate_link' => 'الحساب مرتبط بأكثر من مدرس',
        'deleted_account' => 'الحساب محذوف',
        'inactive_account' => 'الحساب غير نشط',
        'active_account' => 'الحساب نشط',
        'role_granted' => 'صلاحية مدرس مقرر ممنوحة',
        'role_missing' => 'صلاحية مدرس مقرر غير ممنوحة',
        'missing_course_lecturer_role' => 'ينقصه دور مدرس مقرر',
        'ready' => 'جاهز لتوليد الجلسات',
    ],
    'readiness_filter' => [
        'missing_account' => 'لا يوجد حساب مرتبط',
        'linked' => 'مرتبط بحساب',
    ],
    'validation' => [
        'lecturer_already_linked' => 'هذا المدرس مرتبط بحساب دخول مسبقًا.',
        'user_already_linked' => 'هذا الحساب مرتبط بمدرس آخر مسبقًا.',
        'no_linked_user' => 'لا يوجد حساب مرتبط بهذا المدرس.',
    ],
];
