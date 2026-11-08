// Tangkapan layar Fase 3. Hanya membaca; percobaan simpan memakai data tidak valid sehingga tidak tersimpan.
const { chromium } = require('playwright-core');
const path = require('path');
const { BASE, OUT: OUT_DIR, FILES: FILES_DIR, opsiPeramban } = require('./lingkungan');
const OUT = OUT_DIR;
async function shot(page, name, o = {}) { await page.waitForTimeout(o.wait ?? 1000); await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: !!o.full }); console.log('ok', name); }
async function login(browser, email) {
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 }, locale: 'id-ID' });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', email); await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]'); await page.waitForURL(/\/admin$/);
  return { context, page };
}
(async () => {
  const browser = await chromium.launch(opsiPeramban());
  { const { context, page } = await login(browser, 'manajer@spk.test');
    await page.goto(BASE + '/admin/criteria'); await shot(page, '20-kriteria-daftar', { wait: 1500 });
    await page.getByRole('button', { name: 'Atur bobot langsung' }).click(); await shot(page, '21-kriteria-atur-bobot', { wait: 1500 });
    await page.keyboard.press('Escape');
    await page.goto(BASE + '/admin/criteria'); await page.getByRole('button', { name: 'Tambah kriteria' }).click(); await shot(page, '22-kriteria-tambah', { wait: 1500 });
    await page.goto(BASE + '/admin/skala-nilai'); await shot(page, '23-skala-nilai', { full: true, wait: 1800 });
    await page.getByLabel('Nilai asli contoh').fill('2'); await shot(page, '24-skala-uji', { full: true, wait: 1800 });
    const uppers = page.getByLabel('Batas atas (tidak termasuk)');
    await uppers.first().fill('3'); await uppers.first().blur();
    await page.getByRole('button', { name: 'Simpan skala' }).click(); await shot(page, '25-skala-error', { wait: 1800 });
    await context.close(); }
  { const { context, page } = await login(browser, 'admin@spk.test');
    await page.goto(BASE + '/admin/criteria'); await shot(page, '26-kriteria-admin', { wait: 1500 });
    await context.close(); }
  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message); process.exit(1); });
