<?php

namespace App\Filament\Widgets;

use App\Enums\QuestionStatus;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard counts of items waiting for an admin, each linking to the filtered question list.
 */
class ReviewQueue extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    /**
     * @return array{drafts: int, needs_review: int, possible_duplicates: int}
     */
    public static function counts(): array
    {
        return [
            'drafts' => Question::query()->where('status', QuestionStatus::Draft)->count(),
            'needs_review' => Question::query()->where('status', QuestionStatus::NeedsReview)->count(),
            'possible_duplicates' => Question::query()->whereNotNull('duplicate_of_question_id')->count(),
        ];
    }

    protected function getHeading(): ?string
    {
        return __('Review queue');
    }

    protected function getStats(): array
    {
        $counts = self::counts();

        return [
            Stat::make(__('Drafts'), $counts['drafts'])
                ->url(QuestionResource::getUrl('index', ['filters' => ['status' => ['value' => QuestionStatus::Draft->value]]])),
            Stat::make(__('Needs review'), $counts['needs_review'])
                ->color($counts['needs_review'] > 0 ? 'warning' : null)
                ->url(QuestionResource::getUrl('index', ['filters' => ['needs_review' => ['isActive' => true]]])),
            Stat::make(__('Possible duplicates'), $counts['possible_duplicates'])
                ->color($counts['possible_duplicates'] > 0 ? 'warning' : null)
                ->url(QuestionResource::getUrl('index', ['filters' => ['possible_duplicate' => ['isActive' => true]]])),
        ];
    }
}
