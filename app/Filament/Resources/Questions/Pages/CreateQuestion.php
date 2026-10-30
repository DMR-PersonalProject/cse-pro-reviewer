<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Domain\Content\SaveQuestion;
use App\Filament\Resources\Questions\Pages\Concerns\WarnsAboutDuplicates;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuestion extends CreateRecord
{
    use WarnsAboutDuplicates;

    protected static string $resource = QuestionResource::class;

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
