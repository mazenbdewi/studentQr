<?php

use App\Support\WeeklyScheduleRowNormalizer;

it('maps every supported Arabic weekday to its ISO weekday value', function (): void {
    $normalizer = new WeeklyScheduleRowNormalizer;
    $headings = [
        'رمز الشعبة', 'نوع الفئة', 'رمز الفئة', 'اسم المدرس', 'اسم القاعة', 'سعة الفئة', 'عدد الطلاب',
        'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد',
    ];

    expect($normalizer->captureHeadings($headings))->toBe([]);

    $row = $normalizer->mapRow([
        'SUB101', 'T', '1.00', 'مدرس', 'A-01', 20, 18,
        '08:00AM-09:00AM', '09:00AM-10:00AM', '10:00AM-11:00AM', '11:00AM-12:00PM',
        '12:00PM-01:00PM', '01:00PM-02:00PM', '02:00PM-03:00PM',
    ], 2);

    expect(array_keys($row['weekday_values']))->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('normalizes section numbers and parses twelve and twenty-four hour time ranges', function (): void {
    $normalizer = new WeeklyScheduleRowNormalizer;
    $normalizer->captureHeadings([
        'رمز الشعبة', 'نوع الفئة', 'رمز الفئة', 'اسم المدرس', 'اسم القاعة', 'سعة الفئة', 'عدد الطلاب', 'السبت',
    ]);

    $theoretical = $normalizer->normalizeRow($normalizer->mapRow([
        ' deop002 ', 'T', '1.00', ' مدرس  أول ', ' F-03 ', '20.00', '11.00', '08:30AM-10:30AM',
    ], 2));
    $practical = $normalizer->normalizeRow($normalizer->mapRow([
        'DEOP002', 'P', '١.٠٠', null, '0', 20, 11, '14:30-16:30',
    ], 3));

    expect($theoretical['subject_code_key'])->toBe('deop002')
        ->and($theoretical['section_code'])->toBe('T1')
        ->and($theoretical['teacher_name'])->toBe('مدرس أول')
        ->and($theoretical['hall_name'])->toBe('F-03')
        ->and($normalizer->parseTimeRange($theoretical['weekday_values'][6]))->toBe([
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
        ])
        ->and($practical['section_code'])->toBe('P1')
        ->and($practical['hall_name'])->toBeNull()
        ->and($normalizer->parseTimeRange($practical['weekday_values'][6]))->toBe([
            'start_time' => '14:30:00',
            'end_time' => '16:30:00',
        ]);
});

it('treats missing identity values as empty and rejects zero sections and invalid ranges', function (): void {
    $normalizer = new WeeklyScheduleRowNormalizer;

    foreach ([null, '', '  ', 0, '0', '-', 'NaN'] as $value) {
        expect($normalizer->isMissingValue($value))->toBeTrue();
    }

    expect(fn () => $normalizer->parseTimeRange('10:00AM-08:00AM'))
        ->toThrow(InvalidArgumentException::class);

    $normalizer->captureHeadings([
        'رمز الشعبة', 'نوع الفئة', 'رمز الفئة', 'اسم المدرس', 'اسم القاعة', 'سعة الفئة', 'عدد الطلاب', 'السبت',
    ]);
    $zeroSection = $normalizer->normalizeRow($normalizer->mapRow([
        'SUB101', 'T', '0.0', null, null, 0, 0, '08:00AM-09:00AM',
    ], 2));

    expect($zeroSection['section_code'])->toBeNull()
        ->and($normalizer->validateCore($zeroSection))->not->toBeEmpty();
});
