<?php

use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Filament\Resources\Sources\Pages\CreateSource;
use App\Filament\Resources\Sources\Pages\EditSource;
use App\Filament\Resources\Sources\Pages\ListSources;
use App\Models\Question;
use App\Models\Source;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('lists sources with their item counts for admins', function () {
    $this->actingAs($this->admin);
    $source = Source::factory()->create(['name' => 'Reviewer X (2024)']);
    Question::factory()->count(3)->for($source)->create();

    livewire(ListSources::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$source])
        ->assertTableColumnStateSet('questions_count', 3, $source);
});

it('creates a source that defaults to private', function () {
    $this->actingAs($this->admin);

    livewire(CreateSource::class)
        ->assertSchemaStateSet(['visibility' => SourceVisibility::Private])
        ->fillForm(['name' => 'My own notes', 'type' => SourceType::Original->value])
        ->call('create')
        ->assertHasNoFormErrors();

    $source = Source::sole();
    expect($source)
        ->name->toBe('My own notes')
        ->type->toBe(SourceType::Original)
        ->visibility->toBe(SourceVisibility::Private)
        ->created_by->toBe($this->admin->id);
});

it('requires a name and a type', function () {
    $this->actingAs($this->admin);

    livewire(CreateSource::class)
        ->fillForm(['name' => '', 'type' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'type' => 'required']);
});

it('edits a source, including its visibility', function () {
    $this->actingAs($this->admin);
    $source = Source::factory()->create();

    livewire(EditSource::class, ['record' => $source->getRouteKey()])
        ->fillForm(['name' => 'Renamed', 'visibility' => SourceVisibility::Public->value, 'notes' => 'Checked'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($source->fresh())
        ->name->toBe('Renamed')
        ->visibility->toBe(SourceVisibility::Public)
        ->notes->toBe('Checked');
});

it('toggles visibility from the table', function () {
    $this->actingAs($this->admin);
    $source = Source::factory()->create();

    livewire(ListSources::class)->callTableAction('toggleVisibility', $source);
    expect($source->fresh()->visibility)->toBe(SourceVisibility::Public);

    livewire(ListSources::class)->callTableAction('toggleVisibility', $source);
    expect($source->fresh()->visibility)->toBe(SourceVisibility::Private);
});

it('gives users a 403', function (string $url) {
    $source = Source::factory()->create();

    $this->actingAs(User::factory()->withPin()->create())
        ->get(str_replace('{id}', (string) $source->id, $url))
        ->assertForbidden();
})->with(['/admin/sources', '/admin/sources/create', '/admin/sources/{id}/edit']);

it('serves the pages to admins', function () {
    $source = Source::factory()->create();
    $this->actingAs($this->admin);

    $this->get('/admin/sources')->assertOk();
    $this->get('/admin/sources/create')->assertOk();
    $this->get("/admin/sources/{$source->id}/edit")->assertOk();
});
