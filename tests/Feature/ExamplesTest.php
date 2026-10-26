<?php

declare(strict_types=1);

/*
 * The README's usage example is examples/right-to-know.php. This runs it and
 * holds each printed line to the "// ..." comment written under it, so the
 * README cannot drift from what the code does.
 */
it('prints exactly what the README example says it prints', function (): void {
    $source = (string) file_get_contents(__DIR__ . '/../../examples/right-to-know.php');
    preg_match_all('#^// (\w+day \d+ \w+ \d{4})$#m', $source, $promised);

    ob_start();
    require __DIR__ . '/../../examples/right-to-know.php';
    $lines = explode(PHP_EOL, (string) ob_get_clean());

    expect($promised[1])->toHaveCount(3)
        ->and(array_slice($lines, 0, 3))->toBe($promised[1]);
});

it('runs the custom rule example', function (): void {
    ob_start();
    require __DIR__ . '/../../examples/custom-rule.php';
    $output = (string) ob_get_clean();

    expect($output)->toStartWith('Wednesday 16 December 2026')
        ->and($output)->toContain('Day after Thanksgiving')
        ->and($output)->toContain('(Example Code § 5-102)');
});
