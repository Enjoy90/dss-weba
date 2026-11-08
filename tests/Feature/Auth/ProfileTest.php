<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('lets a user change their own name', function (): void {
    $user = User::factory()->create(['name' => 'Nama Lama']);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Nama Baru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Nama Baru');
});

it('keeps the email read only', function (): void {
    $user = User::factory()->create(['email' => 'tetap@spk.test']);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Nama Baru', 'email' => 'ganti@spk.test'])
        ->call('save');

    expect($user->fresh()->email)->toBe('tetap@spk.test');
});

it('changes the password so the old one no longer works', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'sandi-baru-123',
            'passwordConfirmation' => 'sandi-baru-123',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $user->fresh();

    expect(Hash::check('sandi-baru-123', $fresh->password))->toBeTrue()
        ->and(Hash::check('password', $fresh->password))->toBeFalse();
});

it('refuses a password change when the current password is wrong', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'sandi-baru-123',
            'passwordConfirmation' => 'sandi-baru-123',
            'currentPassword' => 'bukan-sandi-lama',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('requires at least eight characters for a new password', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'pendek1',
            'passwordConfirmation' => 'pendek1',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});
