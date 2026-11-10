// Ambil ulang gambar panduan yang memuat daftar order, sesudah kolom Produk disembunyikan bawaannya (Fase 7)
// supaya label Prioritas Utama muat di layar 1366 piksel. Jalankan tepat sesudah php artisan migrate:fresh --seed,
// sebelum ada perhitungan, supaya isi gambar sama dengan gambar Fase 2 dan 5 yang diganti.
const { chromium } = require('playwright-core');
const path = require('path');
const { BASE, OUT, opsiPeramban } = require('./lingkungan');

async function shot(page, name, wait = 900) {
  await page.waitForTimeout(wait);
  await page.screenshot({ path: path.join(OUT, name + '.png') });
  console.log('ok', name);
}

async function masuk(browser, email, viewport = { width: 1600, height: 900 }) {
  const context = await browser.newContext({ viewport, locale: 'id-ID' });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]');
  await page.waitForURL(/\/admin$/);
  return { context, page };
}

(async () => {
  const browser = await chromium.launch(opsiPeramban());

  const manajer = await masuk(browser, 'manajer@spk.test');
  const m = manajer.page;
  await m.goto(BASE + '/admin/production-orders');
  await shot(m, '09-order-daftar');
  await m.locator('button[title=Filter]').click();
  await shot(m, '09a-order-panel-filter', 900);
  await m.getByText('Terlambat', { exact: true }).click();
  await m.getByRole('button', { name: 'Terapkan filter' }).click();
  await shot(m, '10-order-terlambat', 1800);
  await m.goto(BASE + '/admin/production-orders');
  await m.locator('button[title=Filter]').click();
  await m.getByText('Terkunci', { exact: true }).click();
  await m.getByRole('button', { name: 'Terapkan filter' }).click();
  await shot(m, '11-order-terkunci', 1800);
  await m.setViewportSize({ width: 1600, height: 1100 });
  await m.goto(BASE + '/admin/production-orders');
  await shot(m, '40-order-impor-ekspor', 1800);
  await manajer.context.close();

  const supervisor = await masuk(browser, 'supervisor@spk.test');
  await supervisor.page.goto(BASE + '/admin/production-orders');
  await shot(supervisor.page, '17-order-supervisor');
  await supervisor.context.close();

  const pimpinan = await masuk(browser, 'pimpinan@spk.test');
  await pimpinan.page.goto(BASE + '/admin/production-orders');
  await shot(pimpinan.page, '18-order-pimpinan');
  await pimpinan.context.close();

  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 500)); process.exit(1); });
