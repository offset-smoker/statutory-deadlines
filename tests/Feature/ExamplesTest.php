<?php

declare(strict_types=1);

use OffsetSmoker\StatutoryDeadlines\Deadline;

/**
 * The fenced code blocks of README.md in a given language, in order.
 *
 * @return list<string>
 */
function readmeBlocks(string $language): array
{
    $readme = (string) file_get_contents(__DIR__ . '/../../README.md');
    preg_match_all('/^```' . $language . '\n(.*?)^```$/ms', $readme, $blocks);

    return $blocks[1];
}

/**
 * The README block that contains $needle; fails the test if there is not exactly one.
 */
function readmeBlock(string $language, string $needle): string
{
    $matching = array_values(array_filter(readmeBlocks($language), static fn(string $block): bool => str_contains($block, $needle)));

    expect($matching)->toHaveCount(1);

    return $matching[0];
}

/**
 * Runs README PHP blocks as one script, the way a reader pasting them would,
 * and returns the variables they define.
 *
 * @return array<string, mixed>
 */
function runReadmeBlocks(string ...$blocks): array
{
    $script = (string) tempnam(sys_get_temp_dir(), 'readme');
    file_put_contents($script, "<?php\n\ndeclare(strict_types=1);\n\n" . implode("\n", $blocks) . "\nreturn get_defined_vars();\n");

    try {
        /** @var array<string, mixed> */
        return (static fn(): array => require $script)();
    } finally {
        unlink($script);
    }
}

/**
 * Runs examples/right-to-know.php and returns what it printed.
 */
function rightToKnowOutput(): string
{
    ob_start();
    require __DIR__ . '/../../examples/right-to-know.php';

    return (string) ob_get_clean();
}

it('prints exactly what the example says it prints', function (): void {
    $source = (string) file_get_contents(__DIR__ . '/../../examples/right-to-know.php');
    preg_match_all('#^// (\w+day \d+ \w+ \d{4})$#m', $source, $promised);
    $lines = explode(PHP_EOL, rightToKnowOutput());

    expect($promised[1])->toHaveCount(3)
        ->and(array_slice($lines, 0, 3))->toBe($promised[1]);
});

it('shows the example file as the README usage block, line for line', function (): void {
    $source = (string) file_get_contents(__DIR__ . '/../../examples/right-to-know.php');
    // The file adds a header comment, strict types and the autoloader to the README block.
    $body = substr($source, (int) strpos($source, 'use OffsetSmoker'));
    $body = str_replace("require __DIR__ . '/../vendor/autoload.php';\n\n", '', $body);

    expect(readmeBlock('php', 'new PennsylvaniaRightToKnow('))->toBe($body);
});

it('shows the example\'s explanation as the README text block, line for line', function (): void {
    [, $explanation] = explode(PHP_EOL . PHP_EOL, rightToKnowOutput(), 2);

    expect(readmeBlocks('text'))->toBe([$explanation]);
});

it('runs the README block that builds a deadline directly, as written', function (): void {
    $variables = runReadmeBlocks(readmeBlock('php', 'Deadline::from('));

    expect($variables['deadline'])->toBeInstanceOf(Deadline::class)
        ->and((string) $variables['deadline']->dueDate())->toBe('2026-08-10');
});

it('runs the README blocks for a custom calendar and rule, as written', function (): void {
    $variables = runReadmeBlocks(
        readmeBlock('php', "new Calendar('Example State'"),
        readmeBlock('php', 'new DeadlineRule('),
    );

    expect($variables['due'])->toBeInstanceOf(DateTimeImmutable::class);
});

it('runs the custom rule example', function (): void {
    ob_start();
    require __DIR__ . '/../../examples/custom-rule.php';
    $output = (string) ob_get_clean();

    expect($output)->toStartWith('Wednesday 16 December 2026')
        ->and($output)->toContain('Day after Thanksgiving')
        ->and($output)->toContain('(Example Code § 5-102)');
});
