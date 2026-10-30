<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Every admin-editable setting (spec FR-C1): its type, default and validation.
 * Ranges not given in the spec were chosen on 2026-10-30 (CLAUDE.md decisions log).
 */
enum SettingKey: string
{
    // Item split per subtest. CSC doesn't publish it, so it is labeled "unverified".
    case ItemSplitVerbal = 'item_split_verbal';
    case ItemSplitNumerical = 'item_split_numerical';
    case ItemSplitAnalytical = 'item_split_analytical';
    case ItemSplitGeneralInfo = 'item_split_general_info';

    case TotalMinutes = 'total_minutes';
    case PassingGrade = 'passing_grade';
    case RepeatWindowAttempts = 'repeat_window_attempts';
    case AutosaveGraceSeconds = 'autosave_grace_seconds';
    case DuplicateSimilarityThreshold = 'duplicate_similarity_threshold';
    case LockoutMaxAttempts = 'lockout_max_attempts';
    case LockoutWindowMinutes = 'lockout_window_minutes';
    case SuspiciousSecondsPerItem = 'suspicious_seconds_per_item';
    case LeaderboardIncludePrivate = 'leaderboard_include_private';

    public function type(): SettingType
    {
        return match ($this) {
            self::PassingGrade, self::DuplicateSimilarityThreshold => SettingType::Decimal,
            self::LeaderboardIncludePrivate => SettingType::Boolean,
            default => SettingType::Integer,
        };
    }

    public function default(): int|float|bool
    {
        return match ($this) {
            self::ItemSplitVerbal => 50,
            self::ItemSplitNumerical, self::ItemSplitAnalytical, self::ItemSplitGeneralInfo => 40,
            self::TotalMinutes => 190,
            self::PassingGrade => 80.0,
            self::RepeatWindowAttempts => 3,
            self::AutosaveGraceSeconds => 10,
            self::DuplicateSimilarityThreshold => 0.9,
            self::LockoutMaxAttempts => 5,
            self::LockoutWindowMinutes => 15,
            self::SuspiciousSecondsPerItem => 5,
            self::LeaderboardIncludePrivate => false,
        };
    }

    /**
     * Validation rules for a new value, on top of "required".
     *
     * @return list<string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::ItemSplitVerbal, self::ItemSplitNumerical,
            self::ItemSplitAnalytical, self::ItemSplitGeneralInfo => ['integer', 'between:1,100'],
            self::TotalMinutes => ['integer', 'between:10,600'],
            self::PassingGrade => ['numeric', 'decimal:0,2', 'between:0,100'],
            self::RepeatWindowAttempts => ['integer', 'between:0,20'],
            self::AutosaveGraceSeconds => ['integer', 'between:0,60'],
            self::DuplicateSimilarityThreshold => ['numeric', 'decimal:0,2', 'between:0.5,1'],
            self::LockoutMaxAttempts => ['integer', 'between:1,20'],
            self::LockoutWindowMinutes => ['integer', 'between:1,1440'],
            self::SuspiciousSecondsPerItem => ['integer', 'between:0,60'],
            self::LeaderboardIncludePrivate => ['boolean'],
        };
    }

    public function isUnverified(): bool
    {
        return in_array($this, self::itemSplit(), true);
    }

    /**
     * The item split keys in the fixed subtest order, keyed by subtest code.
     *
     * @return array<string, self>
     */
    public static function itemSplit(): array
    {
        return [
            'verbal' => self::ItemSplitVerbal,
            'numerical' => self::ItemSplitNumerical,
            'analytical' => self::ItemSplitAnalytical,
            'general_info' => self::ItemSplitGeneralInfo,
        ];
    }
}
