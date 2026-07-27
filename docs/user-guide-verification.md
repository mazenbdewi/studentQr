# سجل التحقق من دليل الاستخدام

تاريخ التحقق: 28/07/2026 — المرجع: `74dc43b` — المنطقة الزمنية: Asia/Damascus.

| القسم | المسار الفعلي | المصدر المتحقق منه | الدور المسموح | اختبار مرتبط |
|---|---|---|---|---|
| تسجيل الدخول | `/admin/login` | `app/Filament/Pages/Auth/Login.php` | مستخدم لوحة الإدارة | `ProfileIdentityTest` |
| تغيير كلمة المرور | `/password/force-change` | `ForcePasswordChangeController.php` | مستخدم عليه تغيير إلزامي | `LecturerAccountPreparationTest` |
| الملف الشخصي | `/admin/my-profile` | `UsernamePersonalInfo.php` و`UpdatePassword.php` | أدوار لوحة الإدارة | `MyProfileTest` |
| استيراد البيانات | `/admin/manara-enrollment-import` | `ManaraEnrollmentImport.php` | super-admin، admin | `ManaraStudentEnrollmentsImportTest` |
| استيراد الدوام | `/admin/manara-schedule-import` | `ManaraScheduleImport.php` | super-admin، admin | `ManaraScheduleImportPageTest` |
| معالجة المشكلات | `/admin/schedule-import-issues` | `ScheduleImportIssues.php` | صلاحية عرض مشكلات الجدول | `ScheduleImport*` |
| الفصل الحالي | `/admin/current-academic-term` | `AcademicTermManagement.php` | صلاحية إدارة الفصول | `AcademicTermManagementTest` |
| تهيئة الحسابات | `/admin/lecturer-account-preparation` | `LecturerAccountPreparation.php` | super-admin، admin | `LecturerAccountPreparationTest` |
| جلسات المحاضرات | `/admin/lecture-sessions` | `ListLectureSessions.php` و`LectureSessionResource.php` | حسب الصلاحية | `LectureSession*` |
| تقارير الدوام | `/admin/weekly-schedule-reports` | `WeeklyScheduleReports.php` | حسب الصلاحية | `WeeklyScheduleReportsTest` |
| الندوات الإدارية | `/admin/seminars` | `SeminarResource.php` | super-admin، admin، manager | `SeminarAuthorizationTest` |
| سجل التدقيق | `/admin/audit-logs` | `AuditLogResource.php` | حسب الصلاحية | `AuditLog*` |
| رابط الدليل | `/admin/user-guide` و`/admin/user-guide/download` | `UserGuide.php` و`UserGuidePdfService.php` | مستخدم لوحة الإدارة | `UserGuidePdfTest` |
