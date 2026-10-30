<?php

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * The visitor's theme choice (FR-U1). Stored in localStorage under Filament's key and mirrored
 * to the unencrypted `theme` cookie so the server can render an explicit dark theme without a flash.
 */
enum ThemeMode: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public const COOKIE = 'theme';

    public const STORAGE_KEY = 'filament:theme:mode';

    public static function fromRequest(Request $request): self
    {
        return self::tryFrom((string) $request->cookie(self::COOKIE)) ?? self::System;
    }

    public function label(): string
    {
        return match ($this) {
            self::Light => __('Light'),
            self::Dark => __('Dark'),
            self::System => __('System'),
        };
    }
}
