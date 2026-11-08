// Tangkapan layar dan uji peramban untuk Fase 6 (Mesin SAW dan Evaluasi).
// Jalankan sesudah php artisan migrate:fresh --seed, supaya angka sama dengan teks panduan.
const { chromium } = require('playwright-core');
const path = require('path');
const { execFileSync } = require('child_process');
const { BASE, OUT, opsiPeramban } = require('./lingkungan');

async function shot(page, name, wait = 1200) {
  await page.waitForTimeout(wait);
  await page.screenshot({ path: path.join(OUT, name + '.png') });
  console.log('ok', name);
}

async function hitung(page, { centang = true } = {}) {
  await page.getByRole('button', { name: 'Jalankan Perhitungan' }).click();
  await page.waitForTimeout(1200);
  if (centang && (await page.getByLabel('Saya sudah membaca').count()) > 0) {
    await page.getByLabel('Saya sudah membaca').check();
  }
  await page.getByRole('button', { name: 'Hitung sekarang' }).click();
  await page.waitForTimeout(3500);
}

async function baris(page, nomor) {
  const teks = await page.locator('table tbody tr').filter({ hasText: nomor }).first().innerText();
  return teks.replace(/\s+/g, ' ');
}

// Pemilih tanggal Filament hanya bisa dipakai lewat kalender, jadi tanggal masuk diubah dengan satu perintah tinker.
function ubahTanggalMasuk(nomor, hariLalu) {
  const kode = "App\\Models\\ProductionOrder::where('number','" + nomor + "')->update(['order_date' => today()->subDays(" + hariLalu + ")]);";
  execFileSync('php', ['artisan', 'tinker', '--execute=' + kode], { cwd: path.resolve(__dirname, '..', '..', '..'), stdio: 'pipe' });
}

(async () => {
  const browser = await chromium.launch(opsiPeramban());
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 }, locale: 'id-ID' });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', 'manajer@spk.test');
  await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]');
  await page.waitForURL(/\/admin$/);

  // Uji 6.1: halaman dan kesiapan data
  await page.goto(BASE + '/admin/jalankan-perhitungan');
  await shot(page, '50-hitung-halaman', 1800);

  // Uji 6.2: dialog, galat tanpa centang, lalu hitung
  await page.setViewportSize({ width: 1600, height: 1100 });
  await page.getByRole('button', { name: 'Jalankan Perhitungan' }).click();
  await shot(page, '51-hitung-dialog', 1500);
  await page.getByRole('button', { name: 'Hitung sekarang' }).click();
  await shot(page, '52-hitung-galat-centang', 1500);
  await page.getByLabel('Saya sudah membaca').check();
  await page.getByRole('button', { name: 'Hitung sekarang' }).click();
  await page.waitForTimeout(3500);
  await page.setViewportSize({ width: 1600, height: 2100 });
  await shot(page, '53-hitung-hasil', 1000);
  console.log('ORD-2026-0011:', await baris(page, 'ORD-2026-0011'));
  console.log('ORD-2026-0021:', await baris(page, 'ORD-2026-0021'));

  // Uji 6.4: batas 7 dan 8 hari, lewat tanggal masuk ORD-2026-0021
  await page.setViewportSize({ width: 1600, height: 900 });
  ubahTanggalMasuk('ORD-2026-0021', 7);
  await page.goto(BASE + '/admin/jalankan-perhitungan');
  await page.waitForTimeout(1500);
  await hitung(page);
  console.log('7 hari:', await baris(page, 'ORD-2026-0021'));

  ubahTanggalMasuk('ORD-2026-0021', 8);
  await page.goto(BASE + '/admin/jalankan-perhitungan');
  await page.waitForTimeout(1500);
  await hitung(page);
  console.log('8 hari:', await baris(page, 'ORD-2026-0021'));
  await page.setViewportSize({ width: 1600, height: 2100 });
  await shot(page, '54-hitung-eskalasi', 1000);

  // Uji 6.6: perhitungan ditolak bila bobot tidak lagi 100 persen (C7 dinonaktifkan)
  await page.setViewportSize({ width: 1600, height: 900 });
  await page.goto(BASE + '/admin/criteria');
  await page.waitForTimeout(1500);
  const barisC7 = page.locator('table tbody tr').filter({ hasText: 'C7' }).first();
  await barisC7.locator('button').first().click();
  await page.waitForTimeout(600);
  await page.getByRole('menuitem', { name: 'Nonaktifkan' }).click();
  await page.waitForTimeout(800);
  await page.getByRole('button', { name: 'Konfirmasi' }).click();
  await page.waitForTimeout(1500);
  await page.goto(BASE + '/admin/jalankan-perhitungan');
  await shot(page, '55-hitung-ditolak', 1800);
  console.log('tombol nonaktif:', await page.getByRole('button', { name: 'Jalankan Perhitungan' }).isDisabled());

  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 600)); process.exit(1); });
