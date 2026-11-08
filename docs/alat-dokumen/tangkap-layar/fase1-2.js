// Mengambil tangkapan layar aplikasi DSS untuk dokumentasi. Hanya membaca, tidak menyimpan data.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const { BASE, OUT: OUT_DIR, FILES: FILES_DIR, opsiPeramban } = require('./lingkungan');
const OUT = OUT_DIR;
fs.mkdirSync(OUT, { recursive: true });

const log = [];
async function shot(page, name, opts = {}) {
    await page.waitForTimeout(opts.wait ?? 900);
    await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: !!opts.full });
    log.push(name);
}

async function login(browser, email) {
    const context = await browser.newContext({ viewport: { width: 1600, height: 900 }, locale: 'id-ID' });
    const page = await context.newPage();
    await page.goto(BASE + '/admin/login');
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL(/\/admin$/, { timeout: 15000 });
    return { context, page };
}

(async () => {
    const browser = await chromium.launch(opsiPeramban());

    // Halaman masuk dan gagal masuk
    {
        const context = await browser.newContext({ viewport: { width: 1600, height: 900 }, locale: 'id-ID' });
        const page = await context.newPage();
        await page.goto(BASE + '/admin/login');
        await shot(page, '01-masuk');
        await page.fill('input[type=email]', 'admin@spk.test');
        await page.fill('input[type=password]', 'sandi-yang-salah');
        await page.click('button[type=submit]');
        await shot(page, '02-masuk-gagal', { wait: 1500 });
        await context.close();
    }

    // Admin
    {
        const { context, page } = await login(browser, 'admin@spk.test');
        await shot(page, '03-dasbor-admin');
        await page.goto(BASE + '/admin/users');
        await shot(page, '04-pengguna-daftar');
        await page.getByRole('button', { name: 'Tambah pengguna' }).click();
        await shot(page, '05-pengguna-tambah', { wait: 1200 });
        await page.goto(BASE + '/admin/profile');
        await shot(page, '06-profil');
        await page.goto(BASE + '/admin/customers');
        await shot(page, '07-pelanggan-daftar');
        await page.goto(BASE + '/admin/products');
        await shot(page, '08-produk-daftar');
        await context.close();
    }

    // Manajer Produksi
    {
        const { context, page } = await login(browser, 'manajer@spk.test');
        await page.goto(BASE + '/admin/production-orders');
        await shot(page, '09-order-daftar');
        await page.locator('button[title=Filter]').click();
        await shot(page, '09a-order-panel-filter', { wait: 900 });
        await page.getByText('Terlambat', { exact: true }).click();
        await page.getByRole('button', { name: 'Terapkan filter' }).click();
        await shot(page, '10-order-terlambat', { wait: 1800 });
        await page.goto(BASE + '/admin/production-orders');
        await page.locator('button[title=Filter]').click();
        await page.getByText('Terkunci', { exact: true }).click();
        await page.getByRole('button', { name: 'Terapkan filter' }).click();
        await shot(page, '11-order-terkunci', { wait: 1800 });
        await page.goto(BASE + '/admin/production-orders/create');
        await shot(page, '12-order-tambah');
        await page.goto(BASE + '/admin/production-orders');
        await page.locator('table tbody tr').first().locator('button').first().click();
        await shot(page, '13a-order-menu-aksi', { wait: 800 });
        await page.getByText('Ubah status').first().click();
        await shot(page, '13-order-ubah-status', { wait: 1200 });
        await page.goto(BASE + '/admin/production-orders/1');
        await shot(page, '14-order-detail', { full: true, wait: 1500 });
        await page.goto(BASE + '/admin/customers');
        await page.getByRole('button', { name: 'Tambah pelanggan' }).click();
        await shot(page, '15-pelanggan-tambah', { wait: 1200 });
        await context.close();
    }

    // Supervisor
    {
        const { context, page } = await login(browser, 'supervisor@spk.test');
        await shot(page, '16-dasbor-supervisor');
        await page.goto(BASE + '/admin/production-orders');
        await shot(page, '17-order-supervisor');
        await context.close();
    }

    // Pimpinan
    {
        const { context, page } = await login(browser, 'pimpinan@spk.test');
        await page.goto(BASE + '/admin/production-orders');
        await shot(page, '18-order-pimpinan');
        await context.close();
    }

    await browser.close();
    console.log('selesai', log.length, log.join(', '));
})().catch((e) => { console.error('GAGAL', e.message); process.exit(1); });
