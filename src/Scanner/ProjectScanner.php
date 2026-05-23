<?php

namespace Killposer\Scanner;

use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ProjectScanner
{
    private int $permissionErrors = 0;

    /**
     * @return ProjectResult[]
     */
    public function scan(string $rootPath, int $maxDepth = 5): array
    {
        $this->permissionErrors = 0;

        $results = [];
        $this->scanDirectory(rtrim($rootPath, '/'), 0, $maxDepth, $results);

        usort($results, fn (ProjectResult $a, ProjectResult $b) => $b->vendorSize <=> $a->vendorSize);

        return $results;
    }

    public function permissionErrors(): int
    {
        return $this->permissionErrors;
    }

    private function scanDirectory(string $path, int $depth, int $maxDepth, array &$results): void
    {
        if (! is_readable($path)) {
            $this->permissionErrors++;

            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            $this->permissionErrors++;

            return;
        }

        $vendorPath = "{$path}/vendor";

        if (
            in_array('composer.json', $entries, strict: true)
            && is_dir($vendorPath)
            && ! is_link($vendorPath)
        ) {
            $results[] = new ProjectResult(
                path: $path,
                vendorSize: $this->directorySize($vendorPath),
                vendorModifiedAt: new DateTimeImmutable('@' . filemtime($vendorPath)),
            );
        }

        if ($depth >= $maxDepth) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = "{$path}/{$entry}";

            if (! is_dir($entryPath) || is_link($entryPath)) {
                continue;
            }

            // Skip directories that are not worth descending into
            if ($entry === 'vendor' || $entry === 'node_modules' || str_starts_with($entry, '.')) {
                continue;
            }

            $this->scanDirectory($entryPath, $depth + 1, $maxDepth, $results);
        }
    }

    private function directorySize(string $path): int
    {
        $size = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && ! $file->isLink()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable) {
            $this->permissionErrors++;
        }

        return $size;
    }
}
