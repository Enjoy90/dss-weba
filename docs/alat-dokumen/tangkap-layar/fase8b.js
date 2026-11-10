// Tangkapan layar tambahan Fase 8: gambar dasbor lama yang diganti (03 dan 16), laporan dengan periode terbalik,
// dan pratinjau rekap serta riwayat. Jalankan sesudah fase8.js, dengan data demo yang sama.
const { chromium } = require('playwright-core');
const path = require('path');
const { BASE, OUT, opsiPeramban } = require('./lingkungan');

async function shot(page, name, wait = 1500) {
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

async function pilihJenis(page, nama) {
  await page.locator('.fi-fo-select button, .fi-fo-select .fi-select-input-btn').first().click();
  await page.getByRole('option', { name: nama }).click();
  await page.waitForTimeout(2000);
}

(async () => {
  const browser = await chromium.launch(opsiPeramban());

  const admin = await masuk(browser, 'admin@spk.test');
  await shot(admin.page, '03-dasbor-admin', 4500);
  await admin.context.close();

  const supervisor = await masuk(browser, 'supervisor@spk.test');
  await shot(supervisor.page, '16-dasbor-supervisor', 4500);
  await supervisor.context.close();

  const manajer = await masuk(browser, 'manajer@spk.test', { width: 1600, height: 1000 });
  const m = manajer.page;
  await m.goto(BASE + '/admin/laporan');
  await m.waitForTimeout(2000);
  await pilihJenis(m, 'Rekap order (per periode)');
  await shot(m, '87-laporan-rekap', 800);

  // Ganti periode: tanggal awal sesudah tanggal akhir. Kolom tanggal hanya bisa dipilih dari kalender, jadi nilainya
  // diisikan langsung ke komponen Livewire.
  await m.goto(BASE + '/admin/laporan');
  await m.waitForTimeout(2000);
  await pilihJenis(m, 'Order terlambat (per periode)');
  await m.evaluate(() => {
    const komponen = window.Livewire.all().find((c) => c.$wire && c.$wire.data && 'type' in c.$wire.data);
    komponen.$wire.set('data.from', '2026-12-28');
  });
  await m.waitForTimeout(2500);
  console.log('pesan periode terbalik:', await m.getByText('Tanggal akhir tidak boleh sebelum tanggal awal').count());
  await shot(m, '88-laporan-periode-terbalik', 500);
  await manajer.context.close();

  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 700)); process.exit(1); });
