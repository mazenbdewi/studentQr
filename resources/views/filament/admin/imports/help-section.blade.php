@php
    $translate = function (string $key, string $default) {
        $value = __($key);
        return $value === $key ? $default : $value;
    };

    $type = $type ?? 'general';
    $headers = $headers ?? [];
    $sampleRows = $sample_rows ?? [];
    $requiredCols = $required_cols ?? [];
    $optionalCols = $optional_cols ?? [];
    $matchFields = $match_fields ?? [];
    $booleanNote = $boolean_note ?? false;
    $dateNote = $date_note ?? false;
    $timeNote = $time_note ?? false;

    $title = $translate("import-help.modal_title.$type", 'استيراد البيانات من إكسل');
    $intro = $translate("import-help.intro.$type", 'ارفع ملف Excel مطابقًا للمثال التالي، واستخدم نفس أسماء الأعمدة الظاهرة في المثال أو القالب.');
    $instructionsTitle = $translate('import-help.general.help_title', 'تعليمات مهمة');
    $previewTitle = $translate('import-help.general.preview_title', 'مثال على ملف الإكسل المطلوب');
    $requiredTitle = $translate('import-help.general.required_columns', 'الأعمدة المطلوبة');
    $optionalTitle = $translate('import-help.general.optional_columns', 'الأعمدة الاختيارية');
    $matchTitle = $translate('import-help.general.match_fields_title', 'حقول يجب أن تطابق قيماً موجودة في النظام');
    $acceptedTypesTitle = $translate('import-help.general.accepted_file_types', 'الملفات المقبولة');
    $booleanTitle = $translate('import-help.general.boolean_title', 'القيم المنطقية');
    $dateTitle = $translate('import-help.general.date_title', 'صيغة التاريخ');
    $timeTitle = $translate('import-help.general.time_title', 'صيغة الوقت');

    $fieldLabels = [
        'lecturers' => $translate('import-help.match_labels.lecturers', 'أسماء المحاضرين الموجودة في النظام'),
        'departments' => $translate('import-help.match_labels.departments', 'أسماء الأقسام الموجودة في النظام'),
        'faculties' => $translate('import-help.match_labels.faculties', 'أسماء الكليات الموجودة في النظام'),
        'subjects' => $translate('import-help.match_labels.subjects', 'أسماء المواد الموجودة في النظام'),
        'halls' => $translate('import-help.match_labels.halls', 'أسماء القاعات الموجودة في النظام'),
        'students' => $translate('import-help.match_labels.students', 'بيانات الطلاب الموجودة في النظام'),
    ];
@endphp

<div class="space-y-4 text-right" dir="rtl">
    {{-- Intro --}}
    <div class="p-4 border border-gray-700 rounded-xl bg-gray-900/60">
        <h3 class="mb-2 text-base font-bold text-white">
            {{ $title }}
        </h3>

        <p class="text-sm leading-6 text-gray-300">
            {{ $intro }}
        </p>
    </div>

    {{-- Important instructions --}}
    <div class="p-4 border border-gray-700 rounded-xl bg-gray-900/60">
        <h4 class="mb-3 text-sm font-bold text-white">
            {{ $instructionsTitle }}
        </h4>

        <ul class="space-y-2 text-sm leading-6 text-gray-300">
            <li>• {{ $translate('import-help.general.use_same_headers', 'استخدم نفس أسماء الأعمدة الظاهرة في المثال أو القالب.') }}</li>
            <li>• {{ $translate('import-help.general.order_not_important', 'ترتيب الأعمدة غير مهم.') }}</li>
            <li>• {{ $translate('import-help.general.extra_columns_ignored', 'الأعمدة الزائدة غير المستخدمة يتم تجاهلها.') }}</li>
            <li>• {{ $translate('import-help.general.only_excel', 'يُقبل فقط xlsx و xls في نافذة الرفع.') }}</li>
            <li>• {{ $translate('import-help.general.match_existing_records', 'بعض الحقول يجب أن تطابق قيماً موجودة مسبقًا في النظام تمامًا.') }}</li>
        </ul>
    </div>

    {{-- Example table --}}
    <div class="p-4 border border-gray-700 rounded-xl bg-gray-900/60">
        <h4 class="mb-3 text-sm font-bold text-white">
            {{ $previewTitle }}
        </h4>

        <div class="overflow-x-auto border border-gray-700 rounded-lg">
            <table class="min-w-full text-sm text-right border-collapse">
                <thead class="bg-gray-800/80">
                    <tr>
                        @foreach ($headers as $header)
                            <th class="px-4 py-3 font-semibold text-white border border-gray-700 whitespace-nowrap">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="bg-gray-900/40">
                    @forelse ($sampleRows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td class="px-4 py-3 text-gray-200 border border-gray-700 whitespace-nowrap">
                                    {{ $cell }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headers) ?: 1 }}" class="px-4 py-3 text-center text-gray-400 border border-gray-700">
                                {{ $translate('import-help.general.no_example', 'لا يوجد مثال متاح.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 text-sm text-gray-300">
            @if (! empty($requiredCols))
                <div>
                    <span class="font-semibold text-white">{{ $requiredTitle }}:</span>
                    <span>{{ implode(', ', $requiredCols) }}</span>
                </div>
            @endif

            @if (! empty($optionalCols))
                <div>
                    <span class="font-semibold text-white">{{ $optionalTitle }}:</span>
                    <span>{{ implode(', ', $optionalCols) }}</span>
                </div>
            @endif

            @if (! empty($matchFields))
                <div>
                    <div class="mb-1 font-semibold text-white">{{ $matchTitle }}:</div>
                    <ul class="space-y-1">
                        @foreach ($matchFields as $field => $source)
                            <li>
                                • <span class="font-medium text-white">{{ $field }}</span>
                                —
                                {{ $fieldLabels[$source] ?? $source }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <span class="font-semibold text-white">{{ $acceptedTypesTitle }}:</span>
                <span>xlsx, xls</span>
            </div>

            @if ($booleanNote)
                <div>
                    <span class="font-semibold text-white">{{ $booleanTitle }}:</span>
                    <span>{{ $translate('import-help.general.boolean_values', 'yes / no / true / false / 1 / 0') }}</span>
                </div>
            @endif

            @if ($dateNote)
                <div>
                    <span class="font-semibold text-white">{{ $dateTitle }}:</span>
                    <span>dd-mm-yyyy</span>
                </div>
            @endif

            @if ($timeNote)
                <div>
                    <span class="font-semibold text-white">{{ $timeTitle }}:</span>
                    <span>HH:MM</span>
                </div>
            @endif
        </div>
    </div>
</div>