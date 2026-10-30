<?php

namespace App\Filament\Resources\Questions\Pages\Concerns;

use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * FR-Q6: a possible duplicate is a warning with a link to the match. It never blocks saving.
 */
trait WarnsAboutDuplicates
{
    protected function warnAboutDuplicate(Question $question): void
    {
        if (! $question->duplicate_of_question_id) {
            return;
        }

        Notification::make()
            ->warning()
            ->title(__('Possible duplicate'))
            ->body(__('This stem is :percent% similar to item #:id in the same subtest.', [
                'percent' => (int) round((float) $question->duplicate_similarity * 100),
                'id' => $question->duplicate_of_question_id,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('Open #:id', ['id' => $question->duplicate_of_question_id]))
                    ->url(QuestionResource::getUrl('edit', ['record' => $question->duplicate_of_question_id]), shouldOpenInNewTab: true),
            ])
            ->persistent()
            ->send();
    }
}
