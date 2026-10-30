<?php

use App\Domain\Audit\Audit;
use App\Enums\QuestionStatus;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Widgets\ReviewQueue;
use App\Models\Question;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Route;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['full_name' => 'Ana Admin']);
    $this->actingAs($this->admin);
});

it('lists audit entries newest first with who did what', function () {
    $question = Question::factory()->create();
    $old = Audit::record('question.published', $question);
    $old->forceFill(['created_at' => now()->subDay()])->save();
    $new = Audit::record('settings.updated', meta: ['changes' => ['total_minutes' => ['old' => 190, 'new' => 200]]]);

    livewire(ListAuditLogs::class)
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertSee('question.published')
        ->assertSee('Ana Admin');
});

it('shows an entry\'s details read-only', function () {
    $log = Audit::record('settings.updated', meta: ['changes' => ['total_minutes' => ['old' => 190, 'new' => 200]]]);

    livewire(ListAuditLogs::class)
        ->mountAction(TestAction::make('view')->table($log))
        ->assertMountedActionModalSee(['settings.updated', 'total_minutes']);
});

it('is read-only: no create, edit or delete', function () {
    $log = Audit::record('user.pin_reset');

    expect(Route::has('filament.admin.resources.audit-logs.index'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.audit-logs.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.audit-logs.edit'))->toBeFalse()
        ->and(AuditLogResource::canCreate())->toBeFalse()
        ->and(AuditLogResource::canEdit($log))->toBeFalse()
        ->and(AuditLogResource::canDelete($log))->toBeFalse()
        ->and(AuditLogResource::canDeleteAny())->toBeFalse();

    livewire(ListAuditLogs::class)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($log))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($log));
});

it('is admin only', function () {
    $this->get(AuditLogResource::getUrl())->assertOk();

    $this->actingAs(User::factory()->create());
    $this->get(AuditLogResource::getUrl())->assertForbidden();
});

it('counts drafts, needs-review items and possible duplicates', function () {
    Question::factory()->count(2)->create(['status' => QuestionStatus::Draft]);
    Question::factory()->count(3)->create(['status' => QuestionStatus::NeedsReview]);
    $original = Question::factory()->published()->create(['stem' => 'Which article of the 1987 Constitution covers the Bill of Rights?']);
    Question::factory()->published()->create(['subtest_id' => $original->subtest_id, 'stem' => $original->stem]);
    Question::factory()->published()->create(['status' => QuestionStatus::Archived]);

    expect(ReviewQueue::counts())->toBe(['drafts' => 2, 'needs_review' => 3, 'possible_duplicates' => 1]);
});

it('shows the review queue on the dashboard', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(ReviewQueue::class);

    livewire(ReviewQueue::class)
        ->assertSee(__('Drafts'))
        ->assertSee(__('Needs review'))
        ->assertSee(__('Possible duplicates'));
});
