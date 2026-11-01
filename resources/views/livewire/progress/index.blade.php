@php($history = $this->history)
<div class="mx-auto max-w-3xl space-y-8">
    <h1 class="text-lg font-semibold">{{ __('Progress') }}</h1>

    <section aria-labelledby="accuracy-heading" class="space-y-2">
        <h2 id="accuracy-heading" class="text-sm font-medium">{{ __('Accuracy by topic') }}</h2>
        <p class="text-sm text-muted">{{ __('Across your completed attempts. Abandoned attempts are not counted.') }}</p>
        @if ($this->topicAccuracy === [])
            <p class="text-sm text-muted">{{ __('No completed attempts yet.') }}</p>
        @else
            <div class="overflow-x-auto" role="region" aria-labelledby="accuracy-heading" tabindex="0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left">
                            <th scope="col" class="py-1 font-medium">{{ __('Subtest') }}</th>
                            <th scope="col" class="py-1 font-medium">{{ __('Topic') }}</th>
                            <th scope="col" class="py-1 text-right font-medium">{{ __('Correct') }}</th>
                            <th scope="col" class="py-1 text-right font-medium">{{ __('Accuracy') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->topicAccuracy as $row)
                            <tr class="border-b border-line">
                                <td class="py-1">{{ $row['subtest'] }}</td>
                                <td class="py-1">{{ $row['topic'] }}</td>
                                <td class="py-1 text-right tabular-nums">{{ $row['correct'] }} / {{ $row['total'] }}</td>
                                <td class="py-1 text-right tabular-nums">{{ $row['percent'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="history-heading" class="space-y-2">
        <h2 id="history-heading" class="text-sm font-medium">{{ __('History') }}</h2>
        @if ($history->isEmpty())
            <p class="text-sm text-muted">{{ __('No attempts yet.') }}</p>
        @else
            <div class="overflow-x-auto" role="region" aria-labelledby="history-heading" tabindex="0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left">
                            <th scope="col" class="py-1 font-medium">{{ __('Date') }}</th>
                            <th scope="col" class="py-1 font-medium">{{ __('Mode') }}</th>
                            <th scope="col" class="py-1 font-medium">{{ __('Subtest') }}</th>
                            <th scope="col" class="py-1 text-right font-medium">{{ __('Score') }}</th>
                            <th scope="col" class="py-1 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="py-1"><span class="sr-only">{{ __('Open') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $attempt)
                            <tr wire:key="attempt-{{ $attempt->id }}" class="border-b border-line">
                                <td class="py-1">{{ ($attempt->finalized_at ?? $attempt->started_at)->format('M j, Y') }}</td>
                                <td class="py-1">{{ $attempt->mode->label() }} · {{ $attempt->timing->label() }}</td>
                                <td class="py-1">{{ $attempt->subtest?->name ?? __('All subtests') }}</td>
                                <td class="py-1 text-right tabular-nums">{{ $attempt->result ? $attempt->result->general_rating.'%' : '—' }}</td>
                                <td class="py-1">{{ $attempt->status->label() }}</td>
                                <td class="py-1 text-right">
                                    @if ($attempt->isInProgress())
                                        <a href="{{ route('attempts.show', $attempt) }}" class="underline">{{ __('Resume') }}</a>
                                    @else
                                        <a href="{{ route('results.show', $attempt) }}" class="underline">{{ __('Result') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $history->links() }}
        @endif
    </section>
</div>
