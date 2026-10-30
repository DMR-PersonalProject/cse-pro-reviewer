<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Domain\Content\DeleteQuestion;
use App\Domain\Content\SaveQuestion;
use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Resources\Questions\Actions\QuestionActions;
use App\Filament\Resources\Questions\Pages\Concerns\WarnsAboutDuplicates;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Choice;
use App\Models\Question;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Hard delete needs the item ID typed in (M2-11).
 */
class EditQuestion extends EditRecord
{
    use WarnsAboutDuplicates;

    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QuestionActions::publish(),
            QuestionActions::archive(),
            QuestionActions::unarchive(),
            TypedDeleteAction::make(
                expected: fn (Question $record) => (string) $record->id,
                delete: fn (Question $record, string $typed) => app(DeleteQuestion::class)->handle($record, $typed),
                what: __('the item ID'),
                redirectTo: QuestionResource::getUrl('index'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Question $question */
        $question = $this->record;

        return [...$data, 'choices' => $question->choices->map(fn (Choice $choice) => [
            'body' => $choice->body,
            'is_correct' => $choice->is_correct,
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Question $record */
        return app(SaveQuestion::class)->handle($record, $data);
    }

    protected function afterSave(): void
    {
        /** @var Question $question */
        $question = $this->record;
        $this->warnAboutDuplicate($question);
    }
}
