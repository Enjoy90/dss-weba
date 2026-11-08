// Ambil ulang halaman panjang memakai jendela yang cukup tinggi, bukan fullPage, supaya bilah atas tidak tertempel di tengah gambar.
const { chromium } = require('playwright-core');
const path = require('path');
const { BASE, OUT: OUT_DIR, FILES: FILES_DIR, opsiPeramban } = require('./lingkungan');
const OUT = OUT_DIR;
(async () => {
  const browser = await chromium.launch(opsiPeramban());
  const context = await browser.newContext({ viewport: { width: 1600, height: 1000 }, locale: 'id-ID' });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', 'manajer@spk.test'); await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]'); await page.waitForURL(/\/admin$/);
  await page.goto(BASE + '/admin/production-orders/1'); await page.waitForTimeout(1800);
  await page.screenshot({ path: path.join(OUT, '14-order-detail.png') });
  await page.setViewportSize({ width: 1600, height: 1250 });
  await page.goto(BASE + '/admin/production-orders/create'); await page.waitForTimeout(1500);
  await page.screenshot({ path: path.join(OUT, '12-order-tambah.png') });
  await page.setViewportSize({ width: 1600, height: 1560 });
  await page.goto(BASE + '/admin/skala-nilai'); await page.waitForTimeout(1800);
  await page.screenshot({ path: path.join(OUT, '23-skala-nilai.png') });
  await page.getByLabel('Nilai asli contoh').fill('2'); await page.waitForTimeout(1800);
  await page.screenshot({ path: path.join(OUT, '24-skala-uji.png') });
  await browser.close();
  console.log('selesai');
})().catch((e) => { console.error('GAGAL', e.message); process.exit(1); });
