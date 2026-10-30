<?php

namespace App\Filament\Pages;

use App\Domain\Audit\Audit;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Every FR-C1 setting on one page. Saving validates each value, clears the settings cache
 * and writes one audit entry listing what changed. Changes apply to new attempts only (FR-C2).
 *
 * @property-read Schema $form
 */
class ExamSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'exam-settings';

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('System');
    }

    public static function getNavigationLabel(): string
    {
        return __('Exam settings');
    }

    public function getTitle(): string
    {
        return __('Exam settings');
    }

    public function mount(Settings $settings): void
    {
        $this->form->fill(collect(SettingKey::cases())
            ->mapWithKeys(fn (SettingKey $key) => [$key->value => $settings->get($key)])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        $split = SettingKey::itemSplit();
        $others = array_filter(SettingKey::cases(), fn (SettingKey $key) => ! $key->isUnverified());

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Item count per subtest'))
                    ->description(__('CSC doesn’t publish the item count per subtest. These numbers follow common reviewer guidance and are not forced to add up to 170.'))
                    ->afterHeader([
                        Text::make(__('Unverified'))->badge()->color('warning'),
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema(array_map(fn (SettingKey $key) => self::field($key)->live(onBlur: true), array_values($split))),
                        Text::make(fn (Get $get) => __('Total: :total items', [
                            'total' => array_sum(array_map(fn (SettingKey $key) => (int) $get($key->value), $split)),
                        ]))->weight('bold'),
                    ]),
                Section::make(__('Exam and leaderboard'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema(array_map(fn (SettingKey $key) => self::field($key), array_values($others))),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('Save settings'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(Settings $settings): void
    {
        $state = $this->form->getState();
        $changes = [];

        foreach (SettingKey::cases() as $key) {
            $old = $settings->get($key);
            $new = $key->type()->cast($state[$key->value]);

            if ($old !== $new) {
                $changes[$key->value] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes === []) {
            Notification::make()->title(__('Nothing changed'))->send();

            return;
        }

        DB::transaction(function () use ($settings, $changes) {
            foreach ($changes as $key => $change) {
                $settings->set(SettingKey::from($key), $change['new'], Auth::user());
            }

            Audit::record('settings.updated', meta: ['changes' => $changes]);
        });

        Notification::make()->success()->title(__('Settings saved'))
            ->body(__('Changes apply to attempts started from now on.'))
            ->send();
    }

    private static function field(SettingKey $key): Field
    {
        $helper = __('Default: :value', ['value' => $key->format($key->default())]);

        if ($key->type() === SettingType::Boolean) {
            return Toggle::make($key->value)->label($key->label())->helperText($helper);
        }

        return TextInput::make($key->value)
            ->label($key->label())
            ->helperText($helper)
            ->numeric()
            ->step($key->type() === SettingType::Decimal ? 0.01 : 1)
            ->required()
            ->rules($key->rules());
    }
}
