<?php

use App\Services\ScheduleImportSuggestionService;

it('removes exactly one complete outer bracket pair without fuzzy rewriting', function (): void {
    $service = app(ScheduleImportSuggestionService::class);

    expect($service->removeOneOuterBracketPair('[CPFC302]'))->toBe('CPFC302')
        ->and($service->removeOneOuterBracketPair('مدني+عمارة'))->toBe('مدني+عمارة')
        ->and($service->removeOneOuterBracketPair('[[CPFC302]]'))->toBe('[[CPFC302]]')
        ->and($service->removeOneOuterBracketPair('[CPFC302'))->toBe('[CPFC302');
});
