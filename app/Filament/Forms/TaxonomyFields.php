<?php

namespace App\Filament\Forms;

use App\Models\Subtest;
use App\Models\Topic;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Subtest and topic selects: topics follow the chosen subtest, and a topic from another
 * subtest is rejected.
 */
class TaxonomyFields
{
    public static function subtest(): Select
    {
        return Select::make('subtest_id')
            ->label(__('Subtest'))
            ->options(fn () => Subtest::orderBy('position')->pluck('name', 'id'))
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('topic_id', null));
    }

    /**
     * @param  (Closure(Get): bool)|null  $skipCheckWhen  e.g. when a passage decides the topic
     */
    public static function topic(?Closure $skipCheckWhen = null): Select
    {
        return Select::make('topic_id')
            ->label(__('Topic'))
            ->options(fn (Get $get) => Topic::where('subtest_id', $get('subtest_id'))->orderBy('position')->pluck('name', 'id'))
            ->required()
            ->rules([
                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $skipCheckWhen) {
                    if ($skipCheckWhen && $skipCheckWhen($get)) {
                        return;
                    }

                    if (! Topic::whereKey($value)->where('subtest_id', $get('subtest_id'))->exists()) {
                        $fail(__('Pick a topic from the selected subtest.'));
                    }
                },
            ]);
    }
}
