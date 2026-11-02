@php
    $attempt = $this->attempt;
    $result = $attempt->result;
@endphp
{{-- Leftover autosave stash (plan §4.1): sent once for the count, then cleared, so a reload shows nothing. --}}
<div class="mx-auto max-w-3xl space-y-6"
     x-data
     x-init="$store.autosave.reportUnsaved($wire, {{ $attempt->id }})">
    <h1 class="text-lg font-semibold">
        {{ $attempt->subtest?->name ?? $attempt->mode->label() }}
        <span class="text-muted">·</span>
        {{ __('Result') }}
    </h1>

    @if ($unsaved)
        <p role="alert" class="rounded border border-danger px-3 py-2 text-sm text-danger">
            {{ trans_choice(':count answer was not saved because time ran out.|:count answers were not saved because time ran out.', $unsaved) }}
        </p>
    @endif

    @if ($result)
        @php($grade = rtrim(rtrim($result->passing_grade, '0'), '.'))
        <section aria-label="{{ __('Score') }}" class="space-y-2 rounded border border-line bg-surface p-6">
            <p class="text-sm text-muted">{{ $this->isIndividual() ? __('Score') : __('General rating') }}</p>
            <p class="text-5xl font-semibold tabular-nums">{{ $result->general_rating }}%</p>
            <p @class(['font-medium', 'text-danger' => ! $result->passed])>
                @if ($this->isIndividual())
                    {{ $result->passed ? __('Target :grade%: reached', ['grade' => $grade]) : __('Target :grade%: not reached', ['grade' => $grade]) }}
                @else
                    {{ $result->passed ? __('Passed') : __('Failed') }}
                @endif
            </p>
            <p class="text-sm text-muted">{{ __(':correct of :total correct', ['correct' => $result->correct_count, 'total' => $result->total_count]) }}</p>
        </section>

        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
            <dt class="text-muted">{{ __('Time used') }}</dt>
            <dd class="tabular-nums">{{ \App\Domain\Attempts\AttemptClock::format($result->time_used_seconds) }}</dd>
            <dt class="text-muted">{{ __('Ended') }}</dt>
            <dd>{{ $result->end_reason->label() }}</dd>
        </dl>

        <p class="text-sm @if ($this->ineligibleReason) text-muted @endif">
            {{ $this->ineligibleReason?->label() ?? __('Counts toward the leaderboard') }}
        </p>

        @foreach ([
            ['heading' => __('By subtest'), 'label' => __('Subtest'), 'rows' => $this->subtestRows->map(fn ($score) => ['name' => $score->subtest?->name ?? __('Unknown subtest'), 'correct' => $score->correct, 'total' => $score->total, 'percent' => $score->percent])],
            ['heading' => __('By topic'), 'label' => __('Topic'), 'rows' => $this->topicRows],
        ] as $table)
            <section class="space-y-2">
                <h2 class="text-sm font-medium">{{ $table['heading'] }}</h2>
                <div class="overflow-x-auto" role="region" aria-label="{{ $table['heading'] }}" tabindex="0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-left">
                                <th scope="col" class="py-1 font-medium">{{ $table['label'] }}</th>
                                <th scope="col" class="py-1 text-right font-medium">{{ __('Correct') }}</th>
                                <th scope="col" class="py-1 text-right font-medium">{{ __('Score') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($table['rows'] as $row)
                                <tr class="border-b border-line">
                                    <td class="py-1">{{ $row['name'] }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ $row['correct'] }} / {{ $row['total'] }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ $row['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('results.review', $attempt) }}" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Review answers') }}</a>
        <a href="{{ route('practice') }}" class="rounded border border-line px-4 py-2">{{ __('Practice again') }}</a>
        <a href="{{ route('dashboard') }}" class="rounded border border-line px-4 py-2">{{ __('Dashboard') }}</a>
    </div>
</div>
