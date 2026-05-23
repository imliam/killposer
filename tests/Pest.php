<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a temporary directory for a test, returning its path.
 */
function createTempDir(): string
{
    $path = sys_get_temp_dir() . '/killposer-test-' . uniqid();
    mkdir($path, 0755, true);

    return $path;
}

/**
 * Create a Composer project inside $base at $relativePath, with a
 * vendor directory containing a single file of $vendorFileSize bytes.
 */
function createComposerProject(string $base, string $relativePath, int $vendorFileSize = 1024): string
{
    $projectPath = $base . '/' . ltrim($relativePath, '/');

    mkdir($projectPath . '/vendor/package', 0755, true);
    file_put_contents($projectPath . '/composer.json', '{}');
    file_put_contents($projectPath . '/vendor/package/file.php', str_repeat('x', $vendorFileSize));

    return $projectPath;
}

/**
 * Recursively delete a directory.
 */
function deleteDir(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $entries = scandir($path);

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $entryPath = "{$path}/{$entry}";

        is_dir($entryPath) && ! is_link($entryPath)
            ? deleteDir($entryPath)
            : unlink($entryPath);
    }

    rmdir($path);
}
