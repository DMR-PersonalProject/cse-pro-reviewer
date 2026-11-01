<div class="mx-auto max-w-xl space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('Mock exam') }}</h1>

    @if ($this->active)
        <div role="alert" class="space-y-3 rounded border border-line bg-surface px-4 py-3 text-sm">
            <p class="font-medium">{{ __('You already have a test in progress.') }}</p>
            <p class="text-muted">{{ __('Resume it, or abandon it to start a new one. An abandoned test stays in your history and is never ranked.') }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('attempts.show', $this->active) }}" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Resume') }}</a>
                <button type="button" wire:click="abandonActive" wire:confirm="{{ __('Abandon the test in progress?') }}"
                        class="rounded border border-line px-4 py-2 font-medium">{{ __('Abandon') }}</button>
            </div>
        </div>
    @endif

    <form wire:submit="start" class="space-y-6" novalidate>
        <section aria-labelledby="split-heading" class="space-y-2">
            <h2 id="split-heading" class="text-sm font-medium">
                {{ __('Items per subtest') }}
                <span class="ml-1 rounded border border-line px-1.5 py-0.5 text-xs font-normal text-muted">{{ __('Unverified') }}</span>
            </h2>
            <p class="text-sm text-muted">{{ __('The CSC does not publish the split per subtest. This one is an estimate an admin can change.') }}</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left">
                        <th scope="col" class="py-1 font-medium">{{ __('Subtest') }}</th>
                        <th scope="col" class="py-1 text-right font-medium">{{ __('Items') }}</th>
                        <th scope="col" class="py-1 text-right font-medium">{{ __('Available') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->rows as $row)
                        <tr class="border-b border-line">
                            <td class="py-1">{{ $row['name'] }}</td>
                            <td class="py-1 text-right">{{ $row['count'] }}</td>
                            <td class="py-1 text-right @if ($row['available'] < $row['count']) text-danger @endif">{{ $row['available'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <fieldset>
            <legend class="text-sm font-medium">{{ __('Timing') }}</legend>
            <div class="mt-2 space-y-1">
                @foreach ([\App\Enums\AttemptTiming::Timed, \App\Enums\AttemptTiming::Untimed] as $option)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="timing" value="{{ $option->value }}" wire:model.live="timing">
                        <span>{{ $option->label() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div role="status" class="rounded border border-line bg-surface px-4 py-3 text-sm">
            @if ($timing === \App\Enums\AttemptTiming::Timed->value)
                <p>{{ __('One clock of :minutes min for the whole exam. Subtests have no separate time limits.', ['minutes' => $this->totalMinutes]) }}</p>
            @else
                <p>{{ __('No time limit') }}</p>
            @endif
        </div>

        @if ($shortfall !== null)
            <div role="alert" class="space-y-3 rounded border border-line bg-surface px-4 py-3 text-sm">
                <p class="font-medium">{{ __('The bank does not have enough items for a full mock.') }}</p>
                <ul class="list-disc pl-5">
                    @foreach ($shortfall as $code => $counts)
                        <li>{{ __(':subtest: :available of :requested items', ['subtest' => $this->names[$code] ?? $code, 'available' => $counts['available'], 'requested' => $counts['requested']]) }}</li>
                    @endforeach
                </ul>
                @if (array_sum(array_column($shortfall, 'available')) > 0)
                    <p class="text-muted">{{ __('A partial mock is labeled "partial" and is never ranked.') }}</p>
                    <button type="button" wire:click="startPartial" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">
                        {{ __('Start a partial mock') }}
                    </button>
                @endif
            </div>
        @endif

        <button type="submit" @disabled($this->active) class="rounded bg-accent px-4 py-2 font-medium text-on-accent disabled:opacity-50">
            {{ __('Start') }}
        </button>
    </form>
</div>
