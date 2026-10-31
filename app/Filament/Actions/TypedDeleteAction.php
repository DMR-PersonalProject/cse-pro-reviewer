<?php

namespace App\Filament\Actions;

use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * A permanent delete that only runs after the admin types an exact value (FR-Q5).
 */
class TypedDeleteAction
{
    /**
     * @param  Closure(Model): string  $expected  what the admin must type
     * @param  Closure(Model, string, bool): void  $delete  the bool is the purge choice (false when not offered)
     * @param  ?string  $purgeLabel  offers the FR-Q5a purge checkbox, off by default
     */
    public static function make(Closure $expected, Closure $delete, string $what, string $redirectTo, ?string $purgeLabel = null): Action
    {
        return Action::make('delete')
            ->label(__('Delete permanently'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->modalHeading(__('Delete permanently?'))
            ->modalDescription(fn (Model $record) => __('This can’t be undone. Type :what “:expected” to confirm.', [
                'what' => $what,
                'expected' => $expected($record),
            ]))
            ->modalSubmitActionLabel(__('Delete permanently'))
            ->schema(fn (Model $record) => [
                TextInput::make('confirmation')
                    ->label(__('Type :what to confirm', ['what' => $what]))
                    ->required()
                    ->autocomplete(false)
                    ->rules([
                        // Strict comparison: "1.0" must not confirm item 1.
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($expected, $record) {
                            if ((string) $value !== $expected($record)) {
                                $fail(__('That doesn’t match. Nothing was deleted.'));
                            }
                        },
                    ]),
                Checkbox::make('purge')
                    ->label($purgeLabel)
                    ->helperText(__('Past attempts keep their scores, but this content is replaced with a placeholder and its images are deleted.'))
                    ->default(false)
                    ->visible($purgeLabel !== null),
            ])
            ->action(function (Model $record, array $data, Component $livewire) use ($delete, $redirectTo) {
                try {
                    $delete($record, (string) $data['confirmation'], (bool) ($data['purge'] ?? false));
                } catch (DomainException $e) {
                    Notification::make()->danger()->title(__('Not deleted'))->body($e->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title(__('Deleted'))->send();
                $livewire->redirect($redirectTo);
            });
    }
}
