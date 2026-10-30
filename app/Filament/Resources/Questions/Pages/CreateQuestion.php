<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Domain\Content\SaveQuestion;
use App\Filament\Resources\Questions\Pages\Concerns\WarnsAboutDuplicates;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Passage;
use App\Models\Question;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

class CreateQuestion extends CreateRecord
{
    use WarnsAboutDuplicates;

    protected static string $resource = QuestionResource::class;

    /**
     * Set by "New item in this passage" (?passage_id=), so the item starts in that passage.
     */
    #[Url]
    public ?int $passage_id = null;

    protected function afterFill(): void
    {
        if ($passage = Passage::find($this->passage_id)) {
            $this->data['passage_id'] = $passage->id;
            $this->data['subtest_id'] = $passage->subtest_id;
            $this->data['topic_id'] = $passage->topic_id;
            $this->data['source_id'] = $passage->source_id;
            $this->data['language'] = $passage->language->value;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveQuestion::class)->handle(null, $data);
    }

    protected function afterCreate(): void
    {
        /** @var Question $question */
        $question = $this->record;
        $this->warnAboutDuplicate($question);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
