@php($rows = $this->rows)
<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="text-lg font-semibold">{{ __('Leaderboard') }}</h1>

    <div class="flex flex-wrap gap-4">
        <div class="space-y-1">
            <label for="board" class="block text-sm font-medium">{{ __('Board') }}</label>
            <select id="board" wire:model.live="board" class="rounded border border-line bg-surface px-2 py-1 text-sm">
                <option value="mock">{{ __('Full mock exam') }}</option>
                @foreach ($this->subtests as $subtest)
                    <option value="{{ $subtest->code }}">{{ $subtest->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label for="period" class="block text-sm font-medium">{{ __('Period') }}</label>
            <select id="period" wire:model.live="period" class="rounded border border-line bg-surface px-2 py-1 text-sm">
                @foreach (\App\Domain\Leaderboard\Period::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="text-sm text-muted">
        {{ __('Best timed, full-length attempt per player. Players need a PIN to be listed. Weeks start Monday 00:00 Manila time.') }}
    </p>

    @if ($rows->isEmpty())
        <p class="text-sm text-muted">{{ __('No ranked attempts yet for this board and period.') }}</p>
    @else
        <div class="overflow-x-auto" role="region" aria-labelledby="board-heading" tabindex="0">
            <table class="w-full text-sm">
                <caption id="board-heading" class="sr-only">
                    {{ $this->selectedSubtest?->name ?? __('Full mock exam') }} · {{ $this->selectedPeriod->label() }}
                </caption>
                <thead>
                    <tr class="border-b border-line text-left">
                        <th scope="col" class="py-1 font-medium">{{ __('Rank') }}</th>
                        <th scope="col" class="py-1 font-medium">{{ __('Player') }}</th>
                        <th scope="col" class="py-1 text-right font-medium">{{ __('Score') }}</th>
                        <th scope="col" class="py-1 text-right font-medium">{{ __('Time used') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="rank-{{ $row->rank }}" class="border-b border-line">
                            <td class="py-1 tabular-nums">{{ $row->rank }}</td>
                            <td class="py-1">{{ $row->ign }}</td>
                            <td class="py-1 text-right tabular-nums">{{ number_format((float) $row->score, 2) }}%</td>
                            <td class="py-1 text-right tabular-nums">{{ \App\Domain\Attempts\AttemptClock::format((int) $row->time_used_seconds) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    @endif
</div>
