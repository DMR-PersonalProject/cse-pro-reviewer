<?php

namespace App\Filament\Resources\Questions\Actions;

use App\Domain\Content\ArchiveQuestion;
use App\Domain\Content\Exceptions\QuestionNotPublishable;
use App\Domain\Content\PublishQuestion;
use App\Domain\Content\PublishQuestions;
use App\Domain\Content\UnarchiveQuestion;
use App\Enums\QuestionStatus;
use App\Models\Question;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Status actions for the question bank (FR-Q2), shared by the edit page and the table.
 */
class QuestionActions
{
    public static function publish(): Action
    {
        return Action::make('publish')
            ->label(__('Publish'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Question $record) => in_array($record->status, [QuestionStatus::Draft, QuestionStatus::NeedsReview], true))
            ->action(fn (Question $record) => self::run(
                fn () => app(PublishQuestion::class)->handle($record),
                __('Published'),
            ));
    }

    public static function archive(): Action
    {
        return Action::make('archive')
            ->label(__('Archive'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Archived items stop appearing in new tests. Past results are kept.'))
            ->visible(fn (Question $record) => $record->status === QuestionStatus::Published)
            ->action(fn (Question $record) => self::run(
                fn () => app(ArchiveQuestion::class)->handle($record),
                __('Archived'),
            ));
    }

    public static function unarchive(): Action
    {
        return Action::make('unarchive')
            ->label(__('Unarchive'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (Question $record) => $record->status === QuestionStatus::Archived)
            ->action(fn (Question $record) => self::run(
                fn () => app(UnarchiveQuestion::class)->handle($record),
                __('Published again'),
            ));
    }

    public static function bulkPublish(): BulkAction
    {
        return BulkAction::make('publish')
            ->label(__('Publish'))
            ->icon('heroicon-o-check-circle')
            ->action(function (Collection $records) {
                $result = app(PublishQuestions::class)->handle($records);

                $notification = Notification::make()
                    ->title(__('Published :done of :total items', ['done' => count($result->published), 'total' => $result->total()]));

                if ($result->skipped === []) {
                    $notification->success()->send();

                    return;
                }

                $lines = collect($result->skipped)->map(fn (array $blockers, int $id) => "#{$id}: ".implode(' ', $blockers));
                $notification->warning()->body(__('Skipped:')."\n".$lines->implode("\n"))->persistent()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function bulkArchive(): BulkAction
    {
        return self::bulk('archive', __('Archive'), 'heroicon-o-archive-box', ArchiveQuestion::class,
            fn (int $done, int $total) => __('Archived :done of :total items', ['done' => $done, 'total' => $total]));
    }

    public static function bulkUnarchive(): BulkAction
    {
        return self::bulk('unarchive', __('Unarchive'), 'heroicon-o-arrow-uturn-left', UnarchiveQuestion::class,
            fn (int $done, int $total) => __('Unarchived :done of :total items', ['done' => $done, 'total' => $total]));
    }

    /**
     * @param  class-string<ArchiveQuestion|UnarchiveQuestion>  $actionClass
     * @param  Closure(int, int): string  $title
     */
    private static function bulk(string $name, string $label, string $icon, string $actionClass, Closure $title): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($actionClass, $title) {
                $done = 0;
                $skipped = [];

                foreach ($records as $record) {
                    try {
                        app($actionClass)->handle($record);
                        $done++;
                    } catch (DomainException $e) {
                        $skipped[] = "#{$record->id}: ".($e instanceof QuestionNotPublishable ? implode(' ', $e->blockers) : $e->getMessage());
                    }
                }

                $notification = Notification::make()->title($title($done, $records->count()));

                $skipped === []
                    ? $notification->success()->send()
                    : $notification->warning()->body(__('Skipped:')."\n".implode("\n", $skipped))->persistent()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
            Notification::make()->success()->title($successTitle)->send();
        } catch (QuestionNotPublishable $e) {
            Notification::make()->danger()->title(__('Can’t publish yet'))->body(implode("\n", $e->blockers))->persistent()->send();
        } catch (DomainException $e) {
            Notification::make()->danger()->title(__('Not possible'))->body($e->getMessage())->send();
        }
    }
}
