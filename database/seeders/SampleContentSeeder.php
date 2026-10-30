<?php

namespace Database\Seeders;

use App\Domain\Content\AttachMedia;
use App\Domain\Content\PublishQuestion;
use App\Enums\QuestionStatus;
use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Topic;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Original sample items for every topic (FR-Q7), all published under the public source
 * "Original — seed". The items live in data/sample_content.php. Each item must pass the publish
 * gates, or seeding stops. Runs once: if the source already has items, nothing is added.
 */
class SampleContentSeeder extends Seeder
{
    public const SOURCE_NAME = 'Original — seed';

    public function __construct(
        private readonly PublishQuestion $publish,
        private readonly AttachMedia $attachMedia,
    ) {}

    public function run(): void
    {
        $source = Source::firstOrCreate(
            ['name' => self::SOURCE_NAME],
            [
                'type' => SourceType::Original,
                'visibility' => SourceVisibility::Public,
                'notes' => 'Original sample items written for this app. Numbers in tables are fictional.',
            ],
        );

        if ($source->questions()->exists()) {
            return;
        }

        $content = require __DIR__.'/data/sample_content.php';
        $topics = Topic::all()->keyBy('code');

        DB::transaction(function () use ($content, $topics, $source) {
            foreach ($content['passages'] as $data) {
                $topic = $topics[$data['topic']];
                $passage = Passage::create([
                    'subtest_id' => $topic->subtest_id,
                    'topic_id' => $topic->id,
                    'source_id' => $source->id,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'status' => QuestionStatus::Published,
                ]);

                foreach ($data['items'] as $position => $item) {
                    $this->createItem([...$item, 'topic' => $data['topic']], $topic, $source, $passage, $position + 1);
                }
            }

            foreach ($content['items'] as $item) {
                $this->createItem($item, $topics[$item['topic']], $source);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createItem(array $item, Topic $topic, Source $source, ?Passage $passage = null, ?int $position = null): void
    {
        $question = Question::create([
            'subtest_id' => $topic->subtest_id,
            'topic_id' => $topic->id,
            'passage_id' => $passage?->id,
            'passage_position' => $position,
            'source_id' => $source->id,
            'stem' => $item['stem'],
            'explanation' => $item['explanation'],
            'difficulty' => $item['difficulty'] ?? null,
            'language' => $item['language'] ?? 'en',
            'lock_choice_order' => $item['lock_choice_order'] ?? false,
            'status' => QuestionStatus::Draft,
        ]);

        foreach ($item['choices'] as $index => $body) {
            $question->choices()->create([
                'position' => $index + 1,
                'body' => $body,
                'is_correct' => $index + 1 === $item['answer'],
            ]);
        }

        if (isset($item['figure'])) {
            $this->attachFigure($question, $item['figure'], $item['figure_alt']);
        }

        $blockers = $this->publish->blockers($question);

        if ($blockers !== []) {
            throw new RuntimeException("Sample item \"{$item['stem']}\" can't be published: ".implode(' ', $blockers));
        }

        $question->forceFill(['status' => QuestionStatus::Published, 'published_at' => now()])->save();
    }

    private function attachFigure(Question $question, string $figure, string $altText): void
    {
        $image = match ($figure) {
            'rotating_arrow' => $this->rotatingArrowFigure(),
        };

        $path = tempnam(sys_get_temp_dir(), 'cse-figure-');

        try {
            imagepng($image, $path);
            $this->attachMedia->handle($question, $path, $altText);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Four boxes: an arrow pointing up, right and down, then a question mark.
     */
    private function rotatingArrowFigure(): GdImage
    {
        $box = 120;
        $gap = 16;
        $image = imagecreatetruecolor(4 * $box + 5 * $gap, $box + 2 * $gap);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 17, 24, 39);
        imagefill($image, 0, 0, $white);
        imagesetthickness($image, 3);

        foreach ([0, 90, 180, null] as $index => $angle) {
            $left = $gap + $index * ($box + $gap);
            imagerectangle($image, $left, $gap, $left + $box, $gap + $box, $ink);
            $centerX = $left + $box / 2;
            $centerY = $gap + $box / 2;

            if ($angle === null) {
                $this->drawQuestionMark($image, (int) $centerX, (int) $centerY, $ink);

                continue;
            }

            // An upward arrow around the box center, turned clockwise by $angle.
            $shape = [[-8, 34], [8, 34], [8, -8], [22, -8], [0, -36], [-22, -8], [-8, -8]];
            $radians = deg2rad($angle);
            $points = [];

            foreach ($shape as [$x, $y]) {
                $points[] = (int) round($centerX + $x * cos($radians) - $y * sin($radians));
                $points[] = (int) round($centerY + $x * sin($radians) + $y * cos($radians));
            }

            imagefilledpolygon($image, $points, $ink);
        }

        return $image;
    }

    private function drawQuestionMark(GdImage $image, int $centerX, int $centerY, int $ink): void
    {
        $glyph = imagecreatetruecolor(9, 15);
        $white = imagecolorallocate($glyph, 255, 255, 255);
        imagefill($glyph, 0, 0, $white);
        imagecolortransparent($glyph, $white);
        imagestring($glyph, 5, 0, 0, '?', imagecolorallocate($glyph, 17, 24, 39));

        imagecopyresized($image, $glyph, $centerX - 22, $centerY - 37, 0, 0, 45, 75, 9, 15);
    }
}
