<?php

namespace App\Filament\Actions;

use Closure;
use DomainException;
use Filament\Actions\Action;
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
     * @param  Closure(Model, string): void  $delete
     */
    public static function make(Closure $expected, Closure $delete, string $what, string $redirectTo): Action
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
            ])
            ->action(function (Model $record, array $data, Component $livewire) use ($delete, $redirectTo) {
                try {
                    $delete($record, (string) $data['confirmation']);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title(__('Not deleted'))->body($e->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title(__('Deleted'))->send();
                $livewire->redirect($redirectTo);
            });
    }
}
