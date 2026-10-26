<?php

declare(strict_types=1);

/*
 * Supply-chain hardening for CI: every action is pinned to a full commit
 * SHA with the release it came from, and Dependabot keeps the pins current.
 */
it('pins every action in the workflows to a commit SHA with its version', function (): void {
    $workflows = glob(__DIR__ . '/../../.github/workflows/*.yml') ?: [];
    $uses = [];

    foreach ($workflows as $workflow) {
        preg_match_all('/^\s*(?:-\s*)?uses:\s*(.+)$/m', (string) file_get_contents($workflow), $matches);
        $uses = [...$uses, ...$matches[1]];
    }

    expect($workflows)->not->toBeEmpty()
        ->and($uses)->not->toBeEmpty()
        ->and($uses)->each->toMatch('/^[\w.-]+\/[\w.-]+@[0-9a-f]{40} # v?\d+\.\d+\.\d+$/');
});

it('has Dependabot watch the actions and the Composer dependencies', function (): void {
    $path = __DIR__ . '/../../.github/dependabot.yml';

    expect($path)->toBeFile();

    $config = (string) file_get_contents($path);

    expect($config)->toContain('package-ecosystem: github-actions')
        ->and($config)->toContain('package-ecosystem: composer');
});
