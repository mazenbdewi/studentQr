<?php

use App\Support\AcademicTermNormalizer;

it('collapses Unicode whitespace while preserving the Arabic display text', function (): void {
    $normalizer = new AcademicTermNormalizer();

    expect($normalizer->displayName("  الفصل\u{00A0}\tالصيفي   2025/2026  "))
        ->toBe('الفصل الصيفي 2025/2026');
});

it('canonicalizes Arabic and Persian digits without changing Arabic words', function (): void {
    $normalizer = new AcademicTermNormalizer();

    expect($normalizer->canonicalName('الفصل الصيفي ٢٠٢٥/٢٠٢٦'))
        ->toBe('الفصل الصيفي 2025/2026')
        ->and($normalizer->canonicalName('الفصل الصيفي ۲۰۲۵/۲۰۲۶'))
        ->toBe('الفصل الصيفي 2025/2026');
});

it('rejects empty academic term values', function (mixed $value): void {
    $normalizer = new AcademicTermNormalizer();

    expect($normalizer->displayName($value))->toBeNull()
        ->and($normalizer->canonicalName($value))->toBeNull();
})->with([null, '', '   ', '-', 'NaN']);
