<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('logs in an active user and records the last login time', function (): void {
    $user = User::factory()->manager()->create(['email' => 'budi@spk.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'budi@spk.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects an inactive account with a clear message', function (): void {
    User::factory()->inactive()->create(['email' => 'nonaktif@spk.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'nonaktif@spk.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertSee('Akun Anda dinonaktifkan');

    $this->assertGuest();
});

it('does not reveal an inactive account when the password is wrong', function (): void {
    User::factory()->inactive()->create(['email' => 'nonaktif@spk.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'nonaktif@spk.test', 'password' => 'salah-banget'])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertDontSee('Akun Anda dinonaktifkan');
});

it('rejects a wrong password', function (): void {
    User::factory()->create(['email' => 'budi@spk.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'budi@spk.test', 'password' => 'salah-banget'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('locks the login after five failed attempts even with the right password', function (): void {
    User::factory()->create(['email' => 'budi@spk.test']);

    $component = Livewire::test(Login::class)
        ->fillForm(['email' => 'budi@spk.test', 'password' => 'salah-banget']);

    foreach (range(1, 5) as $attempt) {
        $component->call('authenticate');
    }

    $component
        ->fillForm(['email' => 'budi@spk.test', 'password' => 'password'])
        ->call('authenticate');

    $this->assertGuest();
});

it('keeps every panel page closed to guests', function (string $path): void {
    $this->get($path)->assertRedirect('/admin/login');
})->with(['/admin', '/admin/users', '/admin/profile']);
