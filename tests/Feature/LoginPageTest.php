<?php

it('shows the login page in Indonesian', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Masuk')
        ->assertSee('SPK Prioritas Produksi');
});

it('uses Indonesian locale and Jakarta timezone', function (): void {
    expect(config('app.locale'))->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});

it('redirects guests from the panel to the login page', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});
