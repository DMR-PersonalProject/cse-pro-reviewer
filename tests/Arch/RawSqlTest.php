<?php

/**
 * @return array<string, string> path => contents of every PHP file under app/
 */
function appSources(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));
    $sources = [];
    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            $sources[str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname())] = (string) file_get_contents($file->getPathname());
        }
    }

    return $sources;
}

it('allows raw SQL only in LeaderboardQuery', function () {
    $offenders = collect(appSources())
        ->reject(fn (string $source, string $path) => str_ends_with($path, 'app/Domain/Leaderboard/LeaderboardQuery.php'))
        ->filter(fn (string $source) => preg_match('/\b(DB::raw|selectRaw|whereRaw|orWhereRaw|orderByRaw|havingRaw|groupByRaw|fromRaw|DB::statement|DB::select)\b/', $source) === 1)
        ->keys()
        ->all();

    expect($offenders)->toBe([]);
});

it('keeps full_name out of the leaderboard code and view', function () {
    $paths = collect(appSources())
        ->filter(fn (string $source, string $path) => str_contains($path, '/Leaderboard/') && str_contains($source, 'full_name'))
        ->keys()
        ->all();
    $view = dirname(__DIR__, 2).'/resources/views/livewire/leaderboard/index.blade.php';

    expect($paths)->toBe([])
        ->and(is_file($view) ? file_get_contents($view) : '')->not->toContain('full_name');
});
