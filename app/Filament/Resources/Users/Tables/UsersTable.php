<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Identity\ChangeIgn;
use App\Domain\Identity\ChangeRole;
use App\Domain\Identity\DeleteUser;
use App\Domain\Identity\ResetPin;
use App\Domain\Identity\SetUserActive;
use App\Enums\Role;
use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label(__('Username'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ign')
                    ->label(__('IGN'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label(__('Full name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->formatStateUsing(fn (Role $state) => self::roleLabel($state))
                    ->color(fn (Role $state) => $state === Role::Admin ? 'primary' : 'gray'),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
                IconColumn::make('has_pin')
                    ->label(__('Has PIN'))
                    ->state(fn (User $record) => $record->hasPin())
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('username')
            ->filters([
                SelectFilter::make('role')->label(__('Role'))->options(self::roleOptions()),
                TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::changeIgn(),
                    self::changeRole(),
                    self::setActive(false),
                    self::setActive(true),
                    self::resetPin(),
                    TypedDeleteAction::make(
                        expected: fn (User $record) => $record->username,
                        delete: fn (User $record, string $typed) => app(DeleteUser::class)->handle($record, $typed),
                        what: __('the username'),
                        redirectTo: UserResource::getUrl(),
                    )
                        ->modalDescription(fn (User $record) => __('This can’t be undone. The account and its attempts are removed. Type the username “:username” to confirm.', ['username' => $record->username]))
                        ->hidden(fn (User $record) => self::isSelf($record)),
                ]),
            ]);
    }

    private static function changeIgn(): Action
    {
        return Action::make('changeIgn')
            ->label(__('Change IGN'))
            ->icon('heroicon-o-identification')
            ->fillForm(fn (User $record) => ['ign' => $record->ign])
            ->schema(fn (User $record) => [
                TextInput::make('ign')
                    ->label(__('IGN'))
                    ->helperText(__('3–20 letters, digits, spaces, _ or -. Shown on the leaderboard.'))
                    ->required()
                    ->regex(ChangeIgn::PATTERN)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                            $taken = User::query()
                                ->where('ign_normalized', mb_strtolower(trim((string) $value)))
                                ->whereKeyNot($record->getKey())
                                ->exists();

                            if ($taken) {
                                $fail(__('That IGN is already taken.'));
                            }
                        },
                    ]),
            ])
            ->action(fn (User $record, array $data) => self::run(
                fn () => app(ChangeIgn::class)->handle($record, (string) $data['ign']),
                __('IGN changed'),
            ));
    }

    private static function changeRole(): Action
    {
        $promoting = fn (Get $get) => $get('role') === Role::Admin->value;

        return Action::make('changeRole')
            ->label(__('Change role'))
            ->icon('heroicon-o-shield-check')
            ->hidden(fn (User $record) => self::isSelf($record))
            ->modalDescription(__('The account is signed out everywhere. Promoting replaces any PIN with an admin password; demoting removes the password, so the account has no PIN until its owner sets one.'))
            ->fillForm(fn (User $record) => [
                'role' => ($record->isAdmin() ? Role::User : Role::Admin)->value,
                'email' => $record->email,
            ])
            ->schema(fn (User $record) => [
                Select::make('role')
                    ->label(__('New role'))
                    ->options(self::roleOptions())
                    ->required()
                    ->live(),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rules([Rule::unique('users', 'email')->ignore($record->getKey())])
                    ->visible($promoting),
                TextInput::make('password')
                    ->label(__('Admin password'))
                    ->password()
                    ->required()
                    ->confirmed()
                    ->rule(Password::defaults())
                    ->autocomplete('new-password')
                    ->visible($promoting),
                TextInput::make('password_confirmation')
                    ->label(__('Confirm password'))
                    ->password()
                    ->required()
                    ->autocomplete('new-password')
                    ->visible($promoting),
            ])
            ->action(fn (User $record, array $data) => self::run(
                fn () => app(ChangeRole::class)->handle(
                    $record,
                    Role::from($data['role']),
                    $data['email'] ?? null,
                    $data['password'] ?? null,
                    $data['password_confirmation'] ?? null,
                ),
                __('Role changed'),
            ));
    }

    private static function setActive(bool $active): Action
    {
        return Action::make($active ? 'activate' : 'deactivate')
            ->label($active ? __('Activate') : __('Deactivate'))
            ->icon($active ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
            ->color($active ? 'success' : 'warning')
            ->visible(fn (User $record) => $record->is_active !== $active)
            ->requiresConfirmation()
            ->modalDescription($active
                ? __('The account can sign in again.')
                : __('The account is signed out everywhere, can’t sign in and is hidden from the leaderboard. Its data is kept.'))
            ->action(fn (User $record) => self::run(
                fn () => app(SetUserActive::class)->handle($record, $active),
                $active ? __('Activated') : __('Deactivated'),
            ));
    }

    private static function resetPin(): Action
    {
        return Action::make('resetPin')
            ->label(__('Reset PIN'))
            ->icon('heroicon-o-key')
            ->color('warning')
            ->visible(fn (User $record) => $record->hasPin())
            ->requiresConfirmation()
            ->modalDescription(__('The PIN is removed and the account is signed out. Until its owner sets a new PIN, anyone can sign in with the username alone, and the account is off the leaderboard.'))
            ->action(fn (User $record) => self::run(
                fn () => app(ResetPin::class)->handle($record),
                __('PIN reset'),
            ));
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => self::roleLabel($role)])->all();
    }

    private static function roleLabel(Role $role): string
    {
        return match ($role) {
            Role::Admin => __('Admin'),
            Role::User => __('User'),
        };
    }

    private static function isSelf(User $record): bool
    {
        return Auth::user()?->is($record) ?? false;
    }

    private static function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
            Notification::make()->success()->title($successTitle)->send();
        } catch (DomainException $e) {
            Notification::make()->danger()->title(__('Not possible'))->body($e->getMessage())->persistent()->send();
        }
    }
}
