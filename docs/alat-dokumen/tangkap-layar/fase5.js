// Tangkapan layar dan uji peramban untuk Fase 5. Berkas contoh dibuat oleh php artisan orders:sample-import.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const { BASE, OUT: OUT_DIR, FILES: FILES_DIR, opsiPeramban } = require('./lingkungan');
const OUT = OUT_DIR;
const FILES = FILES_DIR;
fs.mkdirSync(FILES, { recursive: true });
async function shot(page, name, wait = 1200, full = false) { await page.waitForTimeout(wait); await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: full }); console.log('ok', name); }
(async () => {
  const browser = await chromium.launch(opsiPeramban());
  const context = await browser.newContext({ viewport: { width: 1600, height: 1100 }, locale: 'id-ID', acceptDownloads: true });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', 'manajer@spk.test'); await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]'); await page.waitForURL(/\/admin$/);

  await page.goto(BASE + '/admin/production-orders'); await shot(page, '40-order-impor-ekspor', 1800);

  // unduh templat dari halaman impor
  await page.goto(BASE + '/admin/impor-order'); await shot(page, '41-impor-halaman', 1500);
  const [tplDownload] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Unduh templat' }).click()]);
  await tplDownload.saveAs(path.join(FILES, 'templat-unduhan.xlsx'));
  console.log('templat', tplDownload.suggestedFilename());

  // unggah berkas contoh dan periksa
  await page.setInputFiles('input[type=file]', path.join(FILES, 'contoh-20-order.xlsx'));
  await page.waitForTimeout(2500);
  await page.getByRole('button', { name: 'Periksa berkas' }).click();
  await page.waitForTimeout(3500);
  await page.setViewportSize({ width: 1600, height: 1700 });
  await shot(page, '42-impor-pratinjau', 1500);

  // impor sekarang
  await page.getByRole('button', { name: 'Impor sekarang' }).click();
  await shot(page, '43-impor-konfirmasi', 1200);
  await page.getByRole('button', { name: 'Ya, impor' }).click();
  await shot(page, '44-impor-hasil', 3500);

  // ekspor dari daftar order dengan filter Dikerjakan
  await page.setViewportSize({ width: 1600, height: 1000 });
  await page.goto(BASE + '/admin/production-orders'); await page.waitForTimeout(1800);
  const [exp] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Ekspor Excel' }).click()]);
  await exp.saveAs(path.join(FILES, 'ekspor-unduhan.xlsx'));
  console.log('ekspor', exp.suggestedFilename());
  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 400)); process.exit(1); });
