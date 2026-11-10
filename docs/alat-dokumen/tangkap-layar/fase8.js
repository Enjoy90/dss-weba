// Tangkapan layar dan uji peramban untuk Fase 8 (dasbor dan laporan).
// Jalankan sesudah: php artisan migrate:fresh --seed lalu php artisan db:seed --class=DemoEvaluationSeeder
// (seeder kedua mengisi lima evaluasi yang diterapkan, supaya semua kartu dan daftar dasbor terisi).
const { chromium } = require('playwright-core');
const path = require('path');
const fs = require('fs');
const { BASE, OUT, opsiPeramban } = require('./lingkungan');

const UNDUH = path.join(__dirname, 'berkas-uji', 'unduhan');
fs.mkdirSync(UNDUH, { recursive: true });

async function shot(page, name, wait = 1500) {
  await page.waitForTimeout(wait);
  await page.screenshot({ path: path.join(OUT, name + '.png') });
  console.log('ok', name);
}

async function masuk(browser, email, viewport = { width: 1600, height: 900 }) {
  const context = await browser.newContext({ viewport, locale: 'id-ID', acceptDownloads: true });
  const page = await context.newPage();
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]');
  await page.waitForURL(/\/admin$/);
  return { context, page };
}

async function lebar(page, label) {
  const r = await page.evaluate(() => ({ s: document.documentElement.scrollWidth, c: document.documentElement.clientWidth }));
  console.log('lebar', label, r.s, r.c, r.s > r.c ? 'MELUAP' : 'pas');
}

async function angkaKartu(page) {
  return page.evaluate(() => Array.from(document.querySelectorAll('.fi-wi-stats-overview-stat')).map((el) => {
    const label = el.querySelector('.fi-wi-stats-overview-stat-label')?.textContent.trim();
    const value = el.querySelector('.fi-wi-stats-overview-stat-value')?.textContent.trim();
    return label + '=' + value;
  }));
}

(async () => {
  const browser = await chromium.launch(opsiPeramban());

  // Manajer: dasbor penuh
  const manajer = await masuk(browser, 'manajer@spk.test', { width: 1600, height: 1400 });
  const m = manajer.page;
  await m.goto(BASE + '/admin');
  await shot(m, '80-dasbor-manajer', 5000);
  console.log('kartu manajer:', (await angkaKartu(m)).join(' | '));
  console.log('tombol Jalankan Perhitungan di dasbor:', await m.getByRole('link', { name: 'Jalankan Perhitungan' }).count());
  console.log('judul widget:', await m.locator('.fi-section-header-heading, .fi-ta-header-heading').allTextContents());

  // Kartu membuka daftar dengan filter yang sama
  const kartu = { 'Terlambat': 'late', 'Terkunci': 'locked', 'Tertahan': 'held', 'Order aktif': null, 'Mendekati tenggat': 'near' };
  for (const [nama] of Object.entries(kartu)) {
    await m.goto(BASE + '/admin');
    await m.waitForTimeout(1500);
    const angka = (await angkaKartu(m)).find((t) => t.startsWith(nama + '='));
    await m.locator('.fi-wi-stats-overview-stat', { hasText: nama }).first().click();
    await m.waitForTimeout(2500);
    const baris = await m.locator('table tbody tr').count();
    const total = await m.locator('.fi-pagination-overview, .fi-ta-pagination-overview').first().textContent().catch(() => '');
    console.log('kartu', angka, '-> daftar baris', baris, '|', (total || '').replace(/\s+/g, ' ').trim());
  }
  await m.goto(BASE + '/admin');
  await m.locator('.fi-wi-stats-overview-stat', { hasText: 'Penilaian belum lengkap' }).first().click();
  await m.waitForTimeout(2500);
  console.log('kartu penilaian belum lengkap -> baris', await m.locator('table tbody tr').count(), m.url());
  await shot(m, '81-dasbor-kartu-penilaian', 500);

  await m.goto(BASE + '/admin/production-orders?filters[held][isActive]=true');
  await shot(m, '82-order-tertahan', 2500);

  // Laporan
  await m.setViewportSize({ width: 1600, height: 1100 });
  await m.goto(BASE + '/admin/laporan');
  await shot(m, '83-laporan-peringkat', 2500);
  await lebar(m, 'laporan 1600');

  const [pdf] = await Promise.all([m.waitForEvent('download'), m.getByRole('button', { name: 'Unduh PDF' }).click()]);
  await pdf.saveAs(path.join(UNDUH, 'peringkat.pdf'));
  console.log('unduh', pdf.suggestedFilename());
  const [xls] = await Promise.all([m.waitForEvent('download'), m.getByRole('button', { name: 'Unduh Excel' }).click()]);
  await xls.saveAs(path.join(UNDUH, 'peringkat.xlsx'));
  console.log('unduh', xls.suggestedFilename());

  // Ganti jenis laporan
  for (const [nama, berkas] of [['Rincian perhitungan SAW (per evaluasi)', 'rincian'], ['Order terlambat (per periode)', 'terlambat'], ['Rekap order (per periode)', 'rekap'], ['Riwayat evaluasi (per periode)', 'riwayat']]) {
    await m.goto(BASE + '/admin/laporan');
    await m.waitForTimeout(1500);
    await m.locator('.fi-fo-select button, .fi-fo-select .fi-select-input-btn').first().click();
    await m.getByRole('option', { name: nama }).click();
    await m.waitForTimeout(2000);
    const [d] = await Promise.all([m.waitForEvent('download'), m.getByRole('button', { name: 'Unduh PDF' }).click()]);
    await d.saveAs(path.join(UNDUH, berkas + '.pdf'));
    const [dx] = await Promise.all([m.waitForEvent('download'), m.getByRole('button', { name: 'Unduh Excel' }).click()]);
    await dx.saveAs(path.join(UNDUH, berkas + '.xlsx'));
    console.log('unduh', berkas, d.suggestedFilename(), dx.suggestedFilename());
    if (berkas === 'rincian') await shot(m, '84-laporan-rincian', 800);
    if (berkas === 'terlambat') await shot(m, '85-laporan-terlambat', 800);
  }
  await manajer.context.close();

  // Pimpinan: ada evaluasi menunggu? Ajukan dahulu lewat tinker di luar skrip bila perlu
  const pimpinan = await masuk(browser, 'pimpinan@spk.test', { width: 1600, height: 1400 });
  const p = pimpinan.page;
  await p.goto(BASE + '/admin');
  await p.waitForTimeout(4500);
  console.log('Pimpinan - Menunggu persetujuan:', await p.getByText('Menunggu persetujuan Anda').count(), '| tombol Jalankan:', await p.getByRole('link', { name: 'Jalankan Perhitungan' }).count());
  await shot(p, '86-dasbor-pimpinan', 500);
  await pimpinan.context.close();

  // Supervisor: tanpa rupiah, tanpa tombol hitung
  const supervisor = await masuk(browser, 'supervisor@spk.test', { width: 1366, height: 900 });
  const s = supervisor.page;
  await s.goto(BASE + '/admin');
  await s.waitForTimeout(3000);
  const teks = await s.locator('body').innerText();
  console.log('Supervisor - ada "Rp":', /Rp\s?\d/.test(teks), '| tombol Jalankan:', await s.getByRole('link', { name: 'Jalankan Perhitungan' }).count(), '| Menunggu persetujuan:', await s.getByText('Menunggu persetujuan Anda').count());
  await lebar(s, 'dasbor supervisor 1366');
  await s.goto(BASE + '/admin/laporan');
  await s.waitForTimeout(2000);
  await s.locator('.fi-fo-select button, .fi-fo-select .fi-select-input-btn').first().click();
  await s.getByRole('option', { name: 'Rincian perhitungan SAW (per evaluasi)' }).click();
  await s.waitForTimeout(2000);
  const rincian = await s.locator('body').innerText();
  console.log('Supervisor - rincian memuat C6:', /\bC6\b/.test(rincian), '| "Rp":', /Rp\s?\d/.test(rincian));
  await lebar(s, 'laporan supervisor 1366');
  await supervisor.context.close();

  // Admin: hanya melihat
  const admin = await masuk(browser, 'admin@spk.test', { width: 1366, height: 900 });
  await admin.page.goto(BASE + '/admin');
  await admin.page.waitForTimeout(3000);
  console.log('Admin - tombol Jalankan:', await admin.page.getByRole('link', { name: 'Jalankan Perhitungan' }).count());
  await lebar(admin.page, 'dasbor admin 1366');
  await admin.context.close();

  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 700)); process.exit(1); });
