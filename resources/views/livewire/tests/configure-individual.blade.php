<div class="mx-auto max-w-xl space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('Practice a subtest') }}</h1>

    @if ($this->active)
        <div role="alert" class="space-y-3 rounded border border-line bg-surface px-4 py-3 text-sm">
            <p class="font-medium">{{ __('You already have a test in progress.') }}</p>
            <p class="text-muted">{{ __('Resume it, or abandon it to start a new one. An abandoned test stays in your history and is never ranked.') }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ url('/attempts/'.$this->active->id) }}" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Resume') }}</a>
                <button type="button" wire:click="abandonActive" wire:confirm="{{ __('Abandon the test in progress?') }}"
                        class="rounded border border-line px-4 py-2 font-medium">{{ __('Abandon') }}</button>
            </div>
        </div>
    @endif

    <form wire:submit="start" class="space-y-6" novalidate>
        <div>
            <label for="subtest" class="block text-sm font-medium">{{ __('Subtest') }}</label>
            <select id="subtest" wire:model.live="subtestId" class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
                @foreach ($this->subtests as $subtest)
                    <option value="{{ $subtest->id }}">{{ $subtest->name }}</option>
                @endforeach
            </select>
            @error('subtestId') <p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
        </div>

        @if ($this->subtest && $this->subtest->topics->isNotEmpty())
            <fieldset>
                <legend class="text-sm font-medium">{{ __('Topics (none checked means all)') }}</legend>
                <div class="mt-2 space-y-1">
                    @foreach ($this->subtest->topics as $topic)
                        <label class="flex items-center gap-2" wire:key="topic-{{ $topic->id }}">
                            <input type="checkbox" value="{{ $topic->id }}" wire:model.live="topicIds">
                            <span>{{ $topic->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('topicIds.*') <p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </fieldset>
        @endif

        <div>
            <label for="count" class="block text-sm font-medium">{{ __('Number of items (5–100)') }}</label>
            <input id="count" type="number" min="5" max="100" wire:model.live.debounce.300ms="count"
                   @error('count') aria-invalid="true" aria-describedby="count-error" @enderror
                   class="mt-1 block w-32 rounded border border-line bg-canvas px-3 py-2">
            @error('count') <p id="count-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="text-sm font-medium">{{ __('Timing') }}</legend>
            <div class="mt-2 space-y-1">
                @foreach (\App\Enums\AttemptTiming::cases() as $option)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="timing" value="{{ $option->value }}" wire:model.live="timing">
                        <span>{{ $option->label() }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-sm text-muted">{{ __('Study shows the answer and explanation after each item and is never ranked.') }}</p>
        </fieldset>

        <div role="status" class="space-y-1 rounded border border-line bg-surface px-4 py-3 text-sm">
            <p>{{ trans_choice(':count item available|:count items available', $this->available) }}</p>
            @if (($minutes = $this->minutesFor((int) $count)) !== null)
                <p>{{ __('Time limit: :minutes min', ['minutes' => $minutes]) }}</p>
            @else
                <p>{{ __('No time limit') }}</p>
            @endif
        </div>

        @if ($shortfallAvailable !== null)
            <div role="alert" class="space-y-3 rounded border border-line bg-surface px-4 py-3 text-sm">
                @if ($shortfallAvailable === 0)
                    <p>{{ __('No items are available for this choice yet.') }}</p>
                @else
                    <p>{{ trans_choice('Only :count item is available.|Only :count items are available.', $shortfallAvailable) }}
                        @if (($shorter = $this->minutesFor($shortfallAvailable)) !== null)
                            {{ __('A shorter test takes :minutes min.', ['minutes' => $shorter]) }}
                        @endif
                    </p>
                    <button type="button" wire:click="startShorter" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">
                        {{ trans_choice('Start with :count item|Start with :count items', $shortfallAvailable) }}
                    </button>
                @endif
            </div>
        @endif

        <button type="submit" @disabled($this->active) class="rounded bg-accent px-4 py-2 font-medium text-on-accent disabled:opacity-50">
            {{ __('Start') }}
        </button>
    </form>
</div>
