<?php

use App\Domain\Content\DuplicateDetector;
use App\Domain\Content\StemNormalizer;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\Question;
use App\Models\Topic;

// 100+ letters with no "z", so replacing the tail with "z" removes exactly that many matches.
const BASE_STEM = 'A farmer sells mangoes at the public market every morning and earns enough to send his children to school';

/**
 * BASE_STEM (normalized) with the last share of characters replaced, so similar_text ≈ 1 - $share.
 */
function variant(float $share): string
{
    $base = app(StemNormalizer::class)->normalize(BASE_STEM);
    $keep = (int) round(mb_strlen($base) * (1 - $share));

    return mb_substr($base, 0, $keep).str_repeat('z', mb_strlen($base) - $keep);
}

it('normalizes stems', function (string $stem, string $expected) {
    expect(app(StemNormalizer::class)->normalize($stem))->toBe($expected);
})->with([
    'case and spaces' => ['  What IS   the Capital ', 'what is the capital'],
    'punctuation' => ['Who wrote "Noli Me Tángere"?!', 'who wrote noli me tángere'],
    'markdown emphasis' => ['Choose the **best** _answer_.', 'choose the best answer'],
    'newlines and tabs' => ["First line\n\n\tsecond line", 'first line second line'],
    'filipino letters' => ['ANG BATÀ AY NIÑO.', 'ang batà ay niño'],
    'symbols kept' => ['What is 5 + 3 = ?', 'what is 5 + 3 ='],
    'dashes and apostrophes' => ["It's a well-known rule", 'it s a well known rule'],
    'empty' => ['  ?! ', ''],
]);

it('fills stem_normalized whenever a question is saved', function () {
    $question = Question::factory()->create(['stem' => 'What  IS this?']);
    expect($question->stem_normalized)->toBe('what is this');

    $question->update(['stem' => 'And NOW?']);
    expect($question->fresh()->stem_normalized)->toBe('and now');
});

it('flags a 95% match and not a 70% match at the default 0.90', function () {
    $topic = Topic::factory()->create();
    $original = Question::factory()->for($topic)->create(['subtest_id' => $topic->subtest_id, 'stem' => BASE_STEM]);
    $detector = app(DuplicateDetector::class);

    $match = $detector->find(variant(0.05), $topic->subtest_id);
    expect($match)->not->toBeNull()
        ->and($match->question->is($original))->toBeTrue()
        ->and($match->similarity)->toBeGreaterThanOrEqual(0.94)->toBeLessThan(0.96);

    expect($detector->find(variant(0.30), $topic->subtest_id))->toBeNull();
});

it('uses the threshold from settings', function () {
    $topic = Topic::factory()->create();
    Question::factory()->for($topic)->create(['subtest_id' => $topic->subtest_id, 'stem' => BASE_STEM]);

    app(Settings::class)->set(SettingKey::DuplicateSimilarityThreshold, 0.65);

    expect(app(DuplicateDetector::class)->find(variant(0.30), $topic->subtest_id))->not->toBeNull();
});

it('still finds a much shorter near-copy at a low threshold', function () {
    $topic = Topic::factory()->create();
    Question::factory()->for($topic)->create(['subtest_id' => $topic->subtest_id, 'stem' => BASE_STEM]);
    app(Settings::class)->set(SettingKey::DuplicateSimilarityThreshold, 0.5);

    // 60% of the length: outside ±30%, but similar_text still reaches 0.75.
    $short = mb_substr(app(StemNormalizer::class)->normalize(BASE_STEM), 0, 63);

    expect(app(DuplicateDetector::class)->find($short, $topic->subtest_id))->not->toBeNull();
});

it('ignores other subtests', function () {
    $here = Topic::factory()->create();
    $elsewhere = Topic::factory()->create();
    Question::factory()->for($elsewhere)->create(['subtest_id' => $elsewhere->subtest_id, 'stem' => BASE_STEM]);

    expect(app(DuplicateDetector::class)->find(BASE_STEM, $here->subtest_id))->toBeNull();
});

it('ignores the question being edited', function () {
    $topic = Topic::factory()->create();
    $question = Question::factory()->for($topic)->create(['subtest_id' => $topic->subtest_id, 'stem' => BASE_STEM]);

    expect(app(DuplicateDetector::class)->find(BASE_STEM, $topic->subtest_id, ignoreQuestionId: $question->id))->toBeNull();
});

it('returns the closest match when several qualify', function () {
    $topic = Topic::factory()->create();
    $attributes = ['subtest_id' => $topic->subtest_id];
    Question::factory()->for($topic)->create([...$attributes, 'stem' => variant(0.08)]);
    $closest = Question::factory()->for($topic)->create([...$attributes, 'stem' => variant(0.02)]);

    expect(app(DuplicateDetector::class)->find(BASE_STEM, $topic->subtest_id)->question->is($closest))->toBeTrue();
});

it('does not flag an empty stem', function () {
    $topic = Topic::factory()->create();
    Question::factory()->for($topic)->create(['subtest_id' => $topic->subtest_id, 'stem' => '?']);

    expect(app(DuplicateDetector::class)->find('!', $topic->subtest_id))->toBeNull();
});
