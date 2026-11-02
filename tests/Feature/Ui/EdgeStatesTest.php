<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptStatus;
use App\Filament\Resources\ImportBatches\Pages\ImportWizard;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Livewire\Auth\SignIn;
use App\Livewire\Tests\ConfigureIndividual;
use App\Livewire\Tests\ConfigureMock;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\ImportBatch;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

use function Pest\Livewire\livewire;

// M7-04: each empty, error and edge state renders its message.

it('renders 419 in the app layout with a way to sign back in', function () {
    Route::middleware('web')->get('/_expired', fn () => abort(419));

    $this->get('/_expired')
        ->assertStatus(419)
        ->assertSee(__('Your session expired'))
        ->assertSee(__('Your answers up to the last save are kept.'))
        ->assertSee('href="'.route('login').'"', escape: false)
        ->assertSee(__('Skip to content'));
});

it('lets a user whose session expired during a test sign back in and resume the same attempt', function () {
    $user = User::factory()->create(['username' => 'juan']);
    $attempt = Attempt::factory()->create(['user_id' => $user->id, 'status' => AttemptStatus::InProgress, 'item_count' => 1]);
    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => Question::factory()]);

    // The session is gone, so opening the attempt again sends the guest to sign in.
    $this->get(route('attempts.show', $attempt))->assertRedirect('/start');

    livewire(SignIn::class)->set('username', 'juan')->call('signIn')
        ->assertRedirect(route('attempts.show', $attempt));

    $this->assertAuthenticatedAs($user);
    $this->get(route('attempts.show', $attempt))->assertOk();
    expect($attempt->fresh()->status)->toBe(AttemptStatus::InProgress);
});

it('says so when a subtest has no published items', function () {
    $subtest = Subtest::factory()->create(['code' => 'numerical', 'position' => 1]);
    $this->actingAs(User::factory()->create());

    livewire(ConfigureIndividual::class)
        ->set('subtestId', $subtest->id)
        ->call('start')
        ->assertNoRedirect()
        ->assertSee(__('No items are available for this choice yet.'))
        ->assertDontSee(__('Start with :count items', ['count' => 0]));

    expect(Attempt::count())->toBe(0);
});

it('says so when the bank is empty and offers no partial mock', function () {
    $this->seed(TaxonomySeeder::class);
    foreach (SettingKey::itemSplit() as $key) {
        app(Settings::class)->set($key, 2);
    }
    $this->actingAs(User::factory()->create());

    livewire(ConfigureMock::class)
        ->call('start')
        ->assertNoRedirect()
        ->assertSee(__('There are no published items yet. An admin needs to publish questions first.'))
        ->assertDontSee(__('Start a partial mock'));

    expect(Attempt::count())->toBe(0);
});

it('explains an import with 0 valid rows and offers only rejecting them', function () {
    $this->seed(TaxonomySeeder::class);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ImportWizard::class)
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('upload.csv', implode("\n", [
            'subtest,topic,language,stem,choice_a,choice_b,choice_c,choice_d,choice_e,answer,explanation,difficulty,tags,source_file,source_page,notes,confidence',
            'numerical,basic_operations,en,Only one choice here?,24,,,,,A,,,,,,,',
        ]))])
        ->call('checkFile')
        ->assertHasNoFormErrors();

    $batch = ImportBatch::sole();
    expect($batch->valid_rows)->toBe(0);

    livewire(ViewImportBatch::class, ['record' => $batch->getRouteKey()])
        ->assertSee(__('No rows can be approved: every row has errors. Fix the file and upload it again.'))
        ->assertActionHidden(TestAction::make('approveValid'))
        ->assertActionVisible(TestAction::make('rejectErrors'));
});

it('warns on the command line when an import has 0 valid rows', function () {
    $this->seed(TaxonomySeeder::class);
    $path = storage_path('framework/testing/zero-valid.csv');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, implode("\n", [
        'subtest,topic,language,stem,choice_a,choice_b,choice_c,choice_d,choice_e,answer,explanation,difficulty,tags,source_file,source_page,notes,confidence',
        'numerical,basic_operations,en,Only one choice here?,24,,,,,A,,,,,,,',
    ]));

    $this->artisan('cse:import', ['path' => $path])
        ->expectsOutputToContain(__('No rows can be approved: every row has errors. Fix the file and upload it again.'))
        ->assertSuccessful();

    @unlink($path);
});
