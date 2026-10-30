<?php

use App\Domain\Audit\Audit;
use App\Models\AuditLog;
use App\Models\User;

it('writes one row with the acting user and the subject', function () {
    $admin = User::factory()->admin()->create();
    $subject = User::factory()->create();
    $this->actingAs($admin);

    Audit::record('user.pin_reset', $subject, 'Asked by the user', ['via' => 'admin']);

    expect(AuditLog::count())->toBe(1);

    $log = AuditLog::sole();
    expect($log)
        ->user_id->toBe($admin->id)
        ->action->toBe('user.pin_reset')
        ->subject_type->toBe($subject->getMorphClass())
        ->subject_id->toBe($subject->id)
        ->reason->toBe('Asked by the user')
        ->meta->toBe(['via' => 'admin'])
        ->and($log->created_at)->not->toBeNull()
        ->and($log->subject->is($subject))->toBeTrue()
        ->and($log->user->is($admin))->toBeTrue();
});

it('records actions with no signed-in user, such as console commands', function () {
    Audit::record('settings.seeded');

    expect(AuditLog::sole())
        ->user_id->toBeNull()
        ->subject_type->toBeNull()
        ->subject_id->toBeNull()
        ->reason->toBeNull()
        ->meta->toBe([]);
});

it('keeps the entry when the acting user is deleted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Audit::record('settings.updated');

    $admin->delete();

    expect(AuditLog::sole()->user_id)->toBeNull();
});
