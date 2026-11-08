<?php

use App\Models\User;

it('recognises the last active admin', function (): void {
    $admin = User::factory()->admin()->create();

    expect($admin->isLastActiveAdmin())->toBeTrue()
        ->and($admin->deactivationBlockedReason(null))->toBe('Admin terakhir tidak dapat dinonaktifkan.');

    User::factory()->admin()->inactive()->create();

    expect($admin->isLastActiveAdmin())->toBeTrue();

    User::factory()->admin()->create();

    expect($admin->isLastActiveAdmin())->toBeFalse()
        ->and($admin->deactivationBlockedReason(null))->toBeNull();
});

it('never treats a non admin as the last admin', function (): void {
    $manager = User::factory()->manager()->create();

    expect($manager->isLastActiveAdmin())->toBeFalse();
});

it('blocks self deactivation before anything else', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    expect($admin->deactivationBlockedReason($admin))->toBe('Anda tidak dapat menonaktifkan akun sendiri.');
});
