<?php

use Killposer\Scanner\ProjectResult;
use Killposer\Scanner\ProjectScanner;

// ── Setup & teardown ────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tmpDir = createTempDir();
    $this->scanner = new ProjectScanner();
});

afterEach(function () {
    deleteDir($this->tmpDir);
});

// ── Discovery ───────────────────────────────────────────────────────────────

it('finds a project that has both composer.json and a vendor directory', function () {
    createComposerProject($this->tmpDir, 'myapp');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(ProjectResult::class);
    expect($results[0]->path)->toBe($this->tmpDir . '/myapp');
});

it('ignores a directory that has composer.json but no vendor directory', function () {
    $projectPath = $this->tmpDir . '/myapp';
    mkdir($projectPath, 0755, true);
    file_put_contents($projectPath . '/composer.json', '{}');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toBeEmpty();
});

it('ignores a directory that has a vendor directory but no composer.json', function () {
    $projectPath = $this->tmpDir . '/myapp';
    mkdir($projectPath . '/vendor', 0755, true);

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toBeEmpty();
});

it('finds multiple projects', function () {
    createComposerProject($this->tmpDir, 'app1');
    createComposerProject($this->tmpDir, 'app2');
    createComposerProject($this->tmpDir, 'app3');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toHaveCount(3);
});

it('finds a project at the scan root itself', function () {
    $projectPath = createComposerProject($this->tmpDir, '.');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toHaveCount(1);
    expect($results[0]->path)->toBe(rtrim($this->tmpDir, '/'));
});

// ── Depth ───────────────────────────────────────────────────────────────────

it('finds a project at the maximum depth', function () {
    createComposerProject($this->tmpDir, 'a/b/c/d/e');

    $results = $this->scanner->scan($this->tmpDir, maxDepth: 5);

    expect($results)->toHaveCount(1);
});

it('does not find a project deeper than the maximum depth', function () {
    createComposerProject($this->tmpDir, 'a/b/c/d/e/f');

    $results = $this->scanner->scan($this->tmpDir, maxDepth: 5);

    expect($results)->toBeEmpty();
});

it('respects a custom depth of 1', function () {
    createComposerProject($this->tmpDir, 'shallow');
    createComposerProject($this->tmpDir, 'deep/nested');

    $results = $this->scanner->scan($this->tmpDir, maxDepth: 1);

    expect($results)->toHaveCount(1);
    expect($results[0]->path)->toBe($this->tmpDir . '/shallow');
});

// ── Skipped directories ──────────────────────────────────────────────────────

it('does not descend into vendor directories during scanning', function () {
    // Create a project whose vendor/ itself contains a composer.json + vendor/
    // (simulating a nested package) — this should not be found.
    $projectPath = createComposerProject($this->tmpDir, 'myapp');
    $nested = $projectPath . '/vendor/nested-package';
    mkdir($nested . '/vendor', 0755, true);
    file_put_contents($nested . '/composer.json', '{}');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toHaveCount(1);
    expect($results[0]->path)->toBe($projectPath);
});

it('does not descend into node_modules directories', function () {
    $projectPath = $this->tmpDir . '/app';
    mkdir($projectPath . '/node_modules/sub-pkg', 0755, true);
    $nested = $projectPath . '/node_modules/sub-pkg';
    mkdir($nested . '/vendor', 0755, true);
    file_put_contents($nested . '/composer.json', '{}');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toBeEmpty();
});

it('does not descend into hidden directories', function () {
    $hidden = $this->tmpDir . '/.hidden';
    createComposerProject($hidden, 'app');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toBeEmpty();
});

it('skips a symlinked vendor directory', function () {
    $projectPath = $this->tmpDir . '/myapp';
    mkdir($projectPath . '/real-vendor/package', 0755, true);
    file_put_contents($projectPath . '/real-vendor/package/file.php', 'x');
    file_put_contents($projectPath . '/composer.json', '{}');
    symlink($projectPath . '/real-vendor', $projectPath . '/vendor');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toBeEmpty();
});

// ── Sorting ──────────────────────────────────────────────────────────────────

it('sorts results by vendor size descending', function () {
    createComposerProject($this->tmpDir, 'small', vendorFileSize: 100);
    createComposerProject($this->tmpDir, 'large', vendorFileSize: 10_000);
    createComposerProject($this->tmpDir, 'medium', vendorFileSize: 1_000);

    $results = $this->scanner->scan($this->tmpDir);

    expect($results[0]->path)->toEndWith('/large');
    expect($results[1]->path)->toEndWith('/medium');
    expect($results[2]->path)->toEndWith('/small');
});

// ── Size calculation ─────────────────────────────────────────────────────────

it('calculates vendor directory size correctly', function () {
    $projectPath = $this->tmpDir . '/myapp';
    mkdir($projectPath . '/vendor', 0755, true);
    file_put_contents($projectPath . '/composer.json', '{}');
    file_put_contents($projectPath . '/vendor/a.php', str_repeat('x', 500));
    file_put_contents($projectPath . '/vendor/b.php', str_repeat('x', 300));

    $results = $this->scanner->scan($this->tmpDir);

    expect($results[0]->vendorSize)->toBe(800);
});

it('reports zero size for an empty vendor directory', function () {
    $projectPath = $this->tmpDir . '/myapp';
    mkdir($projectPath . '/vendor', 0755, true);
    file_put_contents($projectPath . '/composer.json', '{}');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results)->toHaveCount(1);
    expect($results[0]->vendorSize)->toBe(0);
});

it('does not count symlinked files in vendor size', function () {
    $projectPath = $this->tmpDir . '/myapp';
    mkdir($projectPath . '/vendor', 0755, true);
    file_put_contents($projectPath . '/composer.json', '{}');

    $real = $projectPath . '/real-file.php';
    file_put_contents($real, str_repeat('x', 1000));
    symlink($real, $projectPath . '/vendor/link.php');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results[0]->vendorSize)->toBe(0);
});

// ── Metadata ─────────────────────────────────────────────────────────────────

it('records the vendor directory modification time', function () {
    createComposerProject($this->tmpDir, 'myapp');

    $results = $this->scanner->scan($this->tmpDir);

    expect($results[0]->vendorModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);
    // Should be recent (within the last minute)
    expect($results[0]->vendorModifiedAt->getTimestamp())->toBeGreaterThan(time() - 60);
});

// ── Permission errors ─────────────────────────────────────────────────────────

it('counts permission errors for unreadable directories', function () {
    if (posix_getuid() === 0) {
        $this->markTestSkipped('Cannot test permissions as root.');
    }

    createComposerProject($this->tmpDir, 'readable');

    $unreadable = $this->tmpDir . '/locked';
    mkdir($unreadable, 0000, true);

    $this->scanner->scan($this->tmpDir);

    expect($this->scanner->permissionErrors())->toBeGreaterThanOrEqual(1);

    chmod($unreadable, 0755);
    rmdir($unreadable);
});

it('resets permission error count between scans', function () {
    createComposerProject($this->tmpDir, 'myapp');

    $this->scanner->scan($this->tmpDir);
    $this->scanner->scan($this->tmpDir);

    expect($this->scanner->permissionErrors())->toBe(0);
});
