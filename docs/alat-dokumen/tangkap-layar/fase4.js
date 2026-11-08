// Tangkapan layar Fase 4. Hanya membaca; semua percobaan simpan dibatalkan sebelum disimpan.
const { chromium } = require('playwright-core');
const path = require('path');
const { BASE, OUT: OUT_DIR, FILES: FILES_DIR, opsiPeramban } = require('./lingkungan');
const OUT = OUT_DIR;
async function shot(page, name, wait = 1200) { await page.waitForTimeout(wait); await page.screenshot({ path: path.join(OUT, name + '.png') }); console.log('ok', name); }
async function login(browser, email, size) {
  const context = await browser.newContext({ viewport: size, locale: 'id-ID' });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', email); await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]'); await page.waitForURL(/\/admin$/);
  return { context, page };
}
(async () => {
  const browser = await chromium.launch(opsiPeramban());
  {
    const { context, page } = await login(browser, 'manajer@spk.test', { width: 1700, height: 950 });
    await page.goto(BASE + '/admin/penilaian-order'); await shot(page, '30-penilaian-matriks', 2200);
    await page.locator('button[title=Filter]').click();
    await page.locator('[id="tableFiltersForm.incomplete"]').click();
    await page.getByRole('button', { name: 'Terapkan filter' }).click();
    await shot(page, '31-penilaian-belum-lengkap', 2200);
    await page.goto(BASE + '/admin/penilaian-order'); await page.waitForTimeout(1800);
    await page.getByText('16 jam').first().click();
    await shot(page, '32-penilaian-ubah-nilai', 1500);
    await page.keyboard.press('Escape');
    await page.goto(BASE + '/admin/penilaian-order'); await page.waitForTimeout(1800);
    const row = page.locator('tr', { hasText: 'ORD-2026-0014' });
    await row.locator('td').nth(5).click();
    await page.waitForTimeout(1200);
    await page.getByText('Timpa urgensi pelanggan untuk order ini').click();
    await shot(page, '33-penilaian-timpa-urgensi', 1500);
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 1600, height: 1750 });
    await page.goto(BASE + '/admin/production-orders/18'); await shot(page, '34-order-penilaian', 2200);
    await page.goto(BASE + '/admin/production-orders/5'); await shot(page, '35-order-riwayat-nilai', 2200);
    await context.close();
  }
  {
    const { context, page } = await login(browser, 'supervisor@spk.test', { width: 1700, height: 950 });
    await page.goto(BASE + '/admin/penilaian-order'); await shot(page, '36-penilaian-supervisor', 2200);
    await context.close();
  }
  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 300)); process.exit(1); });
