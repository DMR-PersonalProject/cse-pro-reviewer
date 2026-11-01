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
use App\Models\Question;
use App\Models\Subtest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Setup page for the combined mock (FR-G2): timed (one clock for the whole exam) or untimed,
 * the configured split with what the bank has, and the partial-mock offer (FR-G7).
 */
class ConfigureMock extends Component
{
    public string $timing = 'timed';

    /** @var array<string, array{available: int, requested: int}>|null set when a start was refused for a shortfall */
    public ?array $shortfall = null;

    public function mount(FinalizeExpiredAttempts $sweep): void
    {
        // An expired attempt must not block a new start with the resume-or-abandon prompt.
        $sweep->forUser($this->user);
    }

    public function updated(): void
    {
        $this->shortfall = null;
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /**
     * Each subtest in order with its configured count and the published items the user can get.
     *
     * @return Collection<int, array{name: string, count: int, available: int}>
     */
    #[Computed]
    public function rows(): Collection
    {
        $split = app(Settings::class)->itemSplit();
        $subtests = Subtest::query()->whereIn('code', array_keys($split))->orderBy('position')->orderBy('id')->get();
        $available = app(ItemPool::class)->build($this->user, $subtests->modelKeys())->all()
            ->countBy(fn (Question $question) => $question->subtest_id);

        return $subtests->map(fn (Subtest $subtest) => [
            'name' => $subtest->name,
            'count' => $split[$subtest->code],
            'available' => $available[$subtest->id] ?? 0,
        ]);
    }

    /**
     * @return Collection<string, string> subtest code => name
     */
    #[Computed]
    public function names(): Collection
    {
        return Subtest::query()->pluck('name', 'code');
    }

    #[Computed]
    public function totalMinutes(): int
    {
        return (int) app(Settings::class)->get(SettingKey::TotalMinutes);
    }

    #[Computed]
    public function active(): ?Attempt
    {
        return Attempt::query()->where('user_id', $this->user->id)->where('status', AttemptStatus::InProgress)->first();
    }

    public function start(StartAttempt $startAttempt, bool $allowShortfall = false): void
    {
        $this->validate();

        $result = $startAttempt->mock($this->user, AttemptTiming::from($this->timing), $allowShortfall);

        if ($result->attempt !== null) {
            $this->redirect(route('attempts.show', $result->attempt));

            return;
        }

        if ($result->active !== null) {
            unset($this->active);

            return;
        }

        $this->shortfall = $result->subtests;
    }

    public function startPartial(StartAttempt $startAttempt): void
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
            'timing' => ['required', Rule::in([AttemptTiming::Timed->value, AttemptTiming::Untimed->value])],
        ];
    }

    public function render(): View
    {
        return view('livewire.tests.configure-mock')->title(__('Mock exam'));
    }
}
