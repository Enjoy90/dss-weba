<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('shows the user list only to admins', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/users')
        ->assertOk();

    foreach ([User::factory()->manager(), User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create())
            ->get('/admin/users')
            ->assertForbidden();
    }
});

it('hides the users menu from non admin roles', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    expect(UserResource::canViewAny())->toBeFalse();

    $this->actingAs(User::factory()->admin()->create());

    expect(UserResource::canViewAny())->toBeTrue();
});

it('lists users with name, role and status', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $users = User::factory()->count(3)->create();

    Livewire::test(ManageUsers::class)
        ->assertCanSeeTableRecords($users)
        ->assertSee('Supervisor Produksi');
});

it('filters users by role and searches by name', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $manager = User::factory()->manager()->create(['name' => 'Budi Manajer']);
    $supervisor = User::factory()->supervisor()->create(['name' => 'Siti Supervisor']);

    Livewire::test(ManageUsers::class)
        ->filterTable('role', UserRole::Manager->value)
        ->assertCanSeeTableRecords([$manager])
        ->assertCanNotSeeTableRecords([$supervisor])
        ->removeTableFilter('role')
        ->searchTable('Siti')
        ->assertCanSeeTableRecords([$supervisor])
        ->assertCanNotSeeTableRecords([$manager]);
});

it('creates a user who can log in straight away with the initial password', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@spk.test',
            'role' => UserRole::Manager->value,
            'password' => 'sandi-awal-123',
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'baru@spk.test')->firstOrFail();

    expect($created->role)->toBe(UserRole::Manager)
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('sandi-awal-123', $created->password))->toBeTrue();
});

it('rejects a duplicate email and a short initial password', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->create(['email' => 'ada@spk.test']);

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Pengguna Baru',
            'email' => 'ada@spk.test',
            'role' => UserRole::Manager->value,
            'password' => 'pendek',
        ])
        ->assertHasFormErrors(['email', 'password']);
});

it('deactivates another user without deleting the record', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->supervisor()->create();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('deactivate')->table($target));

    expect($target->fresh()->is_active)->toBeFalse();
    $this->assertModelExists($target);
});

it('refuses to deactivate the last active admin', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    User::factory()->admin()->inactive()->create();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('deactivate')->table($admin));

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('refuses to let an admin deactivate their own account', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('deactivate')->table($admin));

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('refuses to demote the last active admin through the edit form', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Manager->value,
            'is_active' => true,
        ])
        ->assertHasFormErrors(['role']);

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('allows demoting an admin when another active admin exists', function (): void {
    $admin = User::factory()->admin()->create();
    $second = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($second), [
            'name' => $second->name,
            'email' => $second->email,
            'role' => UserRole::Manager->value,
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    expect($second->fresh()->role)->toBe(UserRole::Manager);
});

it('reactivates a deactivated user who can then log in again', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->inactive()->create();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('activate')->table($target));

    expect($target->fresh()->is_active)->toBeTrue();
});

it('resets another user password and invalidates the old one', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($target), [
            'password' => 'sandi-reset-123',
            'passwordConfirmation' => 'sandi-reset-123',
        ])
        ->assertHasNoFormErrors();

    $fresh = $target->fresh();

    expect(Hash::check('sandi-reset-123', $fresh->password))->toBeTrue()
        ->and(Hash::check('password', $fresh->password))->toBeFalse();
});

it('has no delete action for users', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->assertTableActionDoesNotExist('delete');
});
