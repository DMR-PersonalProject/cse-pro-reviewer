<?php

namespace App\Filament\Resources\Attempts;

use App\Domain\Attempts\AttemptClock;
use App\Domain\Leaderboard\SetAttemptHidden;
use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Filament\Resources\Attempts\Pages\ListAttempts;
use App\Models\Attempt;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Finished attempts for leaderboard moderation (FR-L4, FR-L5): read-only, with a suspicious
 * badge and filter, and hide/unhide with a required reason. "Suspicious" never hides by itself.
 */
class AttemptResource extends Resource
{
    protected static ?string $model = Attempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('People');
    }

    public static function getModelLabel(): string
    {
        return __('attempt');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'subtest', 'result'])
            ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Timeout]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.ign')->label(__('IGN')),
            TextEntry::make('user.username')->label(__('Username')),
            TextEntry::make('mode')->label(__('Mode'))->formatStateUsing(fn (AttemptMode $state) => $state->label()),
            TextEntry::make('timing')->label(__('Timing'))->formatStateUsing(fn (AttemptTiming $state) => $state->label()),
            TextEntry::make('subtest.name')->label(__('Subtest'))->placeholder(__('All subtests')),
            TextEntry::make('item_count')->label(__('Items')),
            TextEntry::make('result.general_rating')->label(__('Score'))->suffix('%')->placeholder('—'),
            TextEntry::make('time_used_seconds')->label(__('Time used'))->formatStateUsing(fn (?int $state) => AttemptClock::format((int) $state)),
            TextEntry::make('per_item')->label(__('Seconds per item'))->state(fn (Attempt $record) => self::secondsPerItem($record)),
            TextEntry::make('status')->label(__('Status'))->formatStateUsing(fn (AttemptStatus $state) => $state->label()),
            TextEntry::make('started_at')->label(__('Started'))->dateTime(),
            TextEntry::make('finalized_at')->label(__('Finished'))->dateTime(),
            TextEntry::make('is_suspicious')->label(__('Suspicious'))->formatStateUsing(fn (bool $state) => $state ? __('Yes') : __('No')),
            TextEntry::make('hidden_at')->label(__('Hidden'))->dateTime()->placeholder(__('No')),
            TextEntry::make('hidden_reason')->label(__('Hide reason'))->placeholder('—')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('finalized_at')->label(__('Finished'))->dateTime()->sortable(),
                TextColumn::make('user.ign')->label(__('IGN'))->searchable(),
                TextColumn::make('mode')->label(__('Mode'))->formatStateUsing(fn (AttemptMode $state) => $state->label()),
                TextColumn::make('subtest.name')->label(__('Subtest'))->placeholder(__('All subtests')),
                TextColumn::make('result.general_rating')->label(__('Score'))->suffix('%')->placeholder('—'),
                TextColumn::make('time_used_seconds')->label(__('Time used'))->formatStateUsing(fn (?int $state) => AttemptClock::format((int) $state)),
                TextColumn::make('flags')
                    ->label(__('Flags'))
                    ->badge()
                    ->state(fn (Attempt $record) => array_values(array_filter([
                        $record->is_suspicious ? __('Suspicious') : null,
                        $record->hidden_at ? __('Hidden') : null,
                    ])))
                    ->color(fn (string $state) => $state === __('Suspicious') ? 'warning' : 'gray'),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('finalized_at')->orderByDesc('id'))
            ->filters([
                TernaryFilter::make('is_suspicious')->label(__('Suspicious')),
                TernaryFilter::make('hidden_at')->label(__('Hidden'))->nullable(),
                SelectFilter::make('mode')
                    ->label(__('Mode'))
                    ->options(collect(AttemptMode::cases())->mapWithKeys(fn (AttemptMode $mode) => [$mode->value => $mode->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::hideAction(true),
                self::hideAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttempts::route('/'),
        ];
    }

    private static function hideAction(bool $hide): Action
    {
        return Action::make($hide ? 'hide' : 'unhide')
            ->label($hide ? __('Hide from leaderboard') : __('Unhide'))
            ->icon($hide ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->color($hide ? 'danger' : 'gray')
            ->visible(fn (Attempt $record) => ($record->hidden_at === null) === $hide)
            ->modalDescription($hide
                ? __('The attempt leaves every board until it is unhidden. The reason is kept in the audit log.')
                : __('The attempt can rank again if it is otherwise eligible. The reason is kept in the audit log.'))
            ->schema([
                Textarea::make('reason')->label(__('Reason'))->required()->maxLength(255),
            ])
            ->action(function (Attempt $record, array $data) use ($hide) {
                app(SetAttemptHidden::class)->handle($record, $hide, (string) $data['reason']);
                Notification::make()->success()->title($hide ? __('Hidden') : __('Unhidden'))->send();
            });
    }

    private static function secondsPerItem(Attempt $record): string
    {
        return $record->item_count > 0
            ? number_format((int) $record->time_used_seconds / $record->item_count, 1)
            : '—';
    }
}
