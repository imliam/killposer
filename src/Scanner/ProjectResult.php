<?php

namespace Killposer\Scanner;

use DateTimeImmutable;

readonly class ProjectResult
{
    public function __construct(
        public string $path,
        public int $vendorSize,
        public DateTimeImmutable $vendorModifiedAt,
    ) {}

    public function vendorPath(): string
    {
        return $this->path . '/vendor';
    }

    public function relativePath(string $rootPath): string
    {
        $rootPath = rtrim($rootPath, '/');

        if ($this->path === $rootPath) {
            return '.';
        }

        if (str_starts_with($this->path, $rootPath . '/')) {
            return substr($this->path, strlen($rootPath) + 1);
        }

        return $this->path;
    }
}
