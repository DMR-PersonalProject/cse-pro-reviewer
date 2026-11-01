<?php

namespace App\Livewire\Tests;

use App\Domain\Assembly\ItemPool;
use App\Domain\Attempts\AbandonAttempt;
use App\Domain\Attempts\FinalizeExpiredAttempts;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\Subtest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Setup page for an individual test (FR-G1): subtest, topics, count and timing, with a
 * preview of what is available and how long it takes.
 */
class ConfigureIndividual extends Component
{
    public ?int $subtestId = null;

    /** @var list<int> */
    public array $topicIds = [];

    public ?int $count = 20;

    public string $timing = 'timed';

    /** Items available after a start was refused for a shortfall (FR-G7). */
    public ?int $shortfallAvailable = null;

    public function mount(FinalizeExpiredAttempts $sweep): void
    {
        // An expired attempt must not block a new start with the resume-or-abandon prompt.
        $sweep->forUser($this->user);

        $this->subtestId = $this->subtests->first()?->id;
    }

    public function updatedSubtestId(): void
    {
        $this->topicIds = [];
    }

    /**
     * Any change makes an earlier shortfall offer stale.
     */
    public function updated(): void
    {
        $this->shortfallAvailable = null;
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /**
     * @return Collection<int, Subtest>
     */
    #[Computed]
    public function subtests(): Collection
    {
        return Subtest::query()->with('topics')->orderBy('position')->get();
    }

    #[Computed]
    public function subtest(): ?Subtest
    {
        return $this->subtests->firstWhere('id', $this->subtestId);
    }

    #[Computed]
    public function available(): int
    {
        if ($this->subtest === null) {
            return 0;
        }

        return app(ItemPool::class)->build($this->user, [$this->subtest->id], $this->selectedTopicIds())->all()->count();
    }

    /**
     * Null when the timing has no deadline.
     */
    public function minutesFor(int $items): ?int
    {
        if ($this->timing !== AttemptTiming::Timed->value) {
            return null;
        }

        $settings = app(Settings::class);

        return StartAttempt::durationMinutes($items, (int) $settings->get(SettingKey::TotalMinutes), $settings->itemTotal());
    }

    #[Computed]
    public function active(): ?Attempt
    {
        return Attempt::query()->where('user_id', $this->user->id)->where('status', AttemptStatus::InProgress)->first();
    }

    public function start(StartAttempt $startAttempt, bool $allowShortfall = false): void
    {
        $this->validate();

        $result = $startAttempt->individual(
            $this->user,
            $this->subtest,
            $this->selectedTopicIds(),
            $this->count,
            AttemptTiming::from($this->timing),
            $allowShortfall,
        );

        if ($result->attempt !== null) {
            $this->redirect(route('attempts.show', $result->attempt));

            return;
        }

        if ($result->active !== null) {
            unset($this->active);

            return;
        }

        $this->shortfallAvailable = $result->available;
    }

    public function startShorter(StartAttempt $startAttempt): void
    {
        $this->start($startAttempt, allowShortfall: true);
    }

    public function abandonActive(AbandonAttempt $abandon): void
    {
        if ($this->active !== null) {
            $abandon->handle($this->user, $this->active);
        }

        unset($this->active);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'subtestId' => ['required', Rule::exists('subtests', 'id')],
            'topicIds' => ['array'],
            'topicIds.*' => ['integer', Rule::exists('topics', 'id')->where('subtest_id', $this->subtestId)],
            'count' => ['required', 'integer', 'between:5,100'],
            'timing' => ['required', Rule::enum(AttemptTiming::class)],
        ];
    }

    /**
     * @return list<int>
     */
    private function selectedTopicIds(): array
    {
        return array_values(array_map('intval', $this->topicIds));
    }

    public function render(): View
    {
        return view('livewire.tests.configure-individual')->title(__('Practice a subtest'));
    }
}
