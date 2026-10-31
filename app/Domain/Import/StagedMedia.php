<?php

declare(strict_types=1);

namespace App\Domain\Import;

/**
 * Resolves a media path from an import file against the staged folder (plan §8.2). Paths must be
 * relative and stay inside the folder, so a file can never point at anything else on disk.
 */
class StagedMedia
{
    public function isSafe(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:/', $path)) {
            return false;
        }

        return ! in_array('..', preg_split('#[/\\\\]#', $path), true);
    }

    /**
     * The absolute path of an existing staged file, or null when it is unsafe or missing.
     */
    public function resolve(?string $root, string $path): ?string
    {
        if ($root === null || ! $this->isSafe($path)) {
            return null;
        }

        $base = realpath($root);
        $file = realpath($root.DIRECTORY_SEPARATOR.$path);

        if ($base === false || $file === false || ! is_file($file) || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }
}
