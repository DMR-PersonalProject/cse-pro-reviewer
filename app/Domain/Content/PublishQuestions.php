<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exceptions\QuestionNotPublishable;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Bulk publish: publishes every item that passes the gates and reports the rest with reasons.
 */
class PublishQuestions
{
    public function __construct(private readonly PublishQuestion $publish) {}

    /**
     * @param  Collection<int, Question>  $questions
     */
    public function handle(Collection $questions): BulkPublishResult
    {
        $published = [];
        $skipped = [];

        foreach ($questions as $question) {
            try {
                $this->publish->handle($question);
                $published[] = $question->id;
            } catch (QuestionNotPublishable $e) {
                $skipped[$question->id] = $e->blockers;
            }
        }

        return new BulkPublishResult($published, $skipped);
    }
}
