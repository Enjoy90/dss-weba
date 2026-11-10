// Tangkapan layar dan uji peramban untuk Fase 7 (hasil, persetujuan, penerapan, dan perlindungan order lama).
// Jalankan sesudah php artisan migrate:fresh --seed, supaya angka sama dengan teks panduan.
const { chromium } = require('playwright-core');
const path = require('path');
const { BASE, OUT, opsiPeramban } = require('./lingkungan');

async function shot(page, name, wait = 1200) {
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

async function hitung(page) {
  await page.goto(BASE + '/admin/jalankan-perhitungan');
  await page.waitForTimeout(1500);
  await page.getByRole('button', { name: 'Jalankan Perhitungan' }).click();
  await page.waitForTimeout(1200);
  if ((await page.getByLabel('Saya sudah membaca').count()) > 0) await page.getByLabel('Saya sudah membaca').check();
  await page.getByRole('button', { name: 'Hitung sekarang' }).click();
  await page.waitForTimeout(3500);
}

async function tombol(page, nama) {
  return page.getByRole('button', { name: nama, exact: true }).count();
}

(async () => {
  const browser = await chromium.launch(opsiPeramban());

  // Manajer: daftar kosong, hitung, daftar, detail
  const manajer = await masuk(browser, 'manajer@spk.test');
  const m = manajer.page;
  console.log('menu Hasil Evaluasi:', await m.getByRole('link', { name: 'Hasil Evaluasi' }).count());
  await m.goto(BASE + '/admin/evaluations');
  await shot(m, '60-evaluasi-kosong', 1500);

  await hitung(m);
  await m.goto(BASE + '/admin/evaluations');
  await shot(m, '61-evaluasi-daftar', 1500);

  await m.locator('table tbody tr').first().click();
  await m.waitForURL(/\/admin\/evaluations\/\d+/);
  await m.setViewportSize({ width: 1600, height: 1500 });
  await shot(m, '62-evaluasi-detail', 2000);
  console.log('tombol awal: Ajukan', await tombol(m, 'Ajukan untuk disetujui'), '| Setujui', await tombol(m, 'Setujui'), '| Terapkan', await tombol(m, 'Terapkan prioritas'));

  await m.setViewportSize({ width: 1600, height: 1000 });
  await m.getByRole('tab', { name: 'Perhitungan skor' }).click();
  await shot(m, '63-evaluasi-tab-skor', 1200);
  await m.getByRole('tab', { name: /Peringatan/ }).click();
  await shot(m, '64-evaluasi-tab-peringatan', 1200);
  await m.getByRole('tab', { name: 'Ringkasan dan peringkat' }).click();
  await m.waitForTimeout(800);

  // Penyesuaian: order terkunci ke bawah order biasa ditolak, order biasa naik diterima
  const barisKunci = m.locator('table tbody tr').filter({ hasText: 'ORD-2026-0005' }).first();
  await barisKunci.getByRole('button', { name: 'Sesuaikan peringkat' }).click();
  await m.waitForTimeout(1000);
  await m.getByLabel('Peringkat baru').fill('21');
  await m.getByLabel('Alasan penyesuaian').fill('Uji: coba turunkan order terkunci');
  await m.getByRole('button', { name: 'Simpan peringkat' }).click();
  await shot(m, '65-evaluasi-sesuaikan-ditolak', 4000);
  console.log('ditolak:', (await m.locator('.fi-no-notification').allInnerTexts()).map((t) => t.replace(/\s+/g, ' ')));
  await m.reload();
  await m.waitForTimeout(1500);

  const barisBiasa = m.locator('table tbody tr').filter({ hasText: 'ORD-2026-0021' }).first();
  await barisBiasa.getByRole('button', { name: 'Sesuaikan peringkat' }).click();
  await m.waitForTimeout(1000);
  await m.getByLabel('Peringkat baru').fill('12');
  await m.getByLabel('Alasan penyesuaian').fill('Pelanggan meminta dikirim lebih awal');
  await shot(m, '66-evaluasi-sesuaikan-form', 800);
  await m.getByRole('button', { name: 'Simpan peringkat' }).click();
  await m.waitForTimeout(3500);
  console.log('ORD-0021 setelah disesuaikan:', (await m.locator('table tbody tr').filter({ hasText: 'ORD-2026-0021' }).first().innerText()).replace(/\s+/g, ' '));

  // Ajukan
  await m.getByRole('button', { name: 'Ajukan untuk disetujui' }).click();
  await m.waitForTimeout(1000);
  await m.getByRole('button', { name: 'Ya, ajukan' }).click();
  await m.waitForTimeout(3500);
  console.log('status setelah ajukan:', await m.getByText('Diajukan').count() > 0, '| Terapkan terlihat:', await tombol(m, 'Terapkan prioritas'));
  const urlEvaluasi = m.url();

  // Pimpinan: tolak dulu, ajukan ulang oleh manajer, lalu setujui
  const pimpinan = await masuk(browser, 'pimpinan@spk.test');
  const p = pimpinan.page;
  await p.goto(urlEvaluasi);
  await p.setViewportSize({ width: 1600, height: 1000 });
  await p.waitForTimeout(2000);
  console.log('pimpinan: Setujui', await tombol(p, 'Setujui'), '| Tolak', await tombol(p, 'Tolak'), '| Ajukan', await tombol(p, 'Ajukan untuk disetujui'));
  await p.getByRole('button', { name: 'Tolak', exact: true }).click();
  await p.waitForTimeout(1000);
  await p.getByLabel('Catatan penolakan').fill('Urutan ORD-2026-0021 belum sesuai, mohon periksa lagi');
  await shot(p, '67-evaluasi-tolak-form', 600);
  await p.getByRole('button', { name: 'Tolak evaluasi' }).click();
  await shot(p, '68-evaluasi-ditolak', 2200);

  await m.reload();
  await m.waitForTimeout(1800);
  await m.getByRole('button', { name: 'Ajukan untuk disetujui' }).click();
  await m.waitForTimeout(1000);
  await m.getByRole('button', { name: 'Ya, ajukan' }).click();
  await m.waitForTimeout(3500);

  await p.reload();
  await p.waitForTimeout(1800);
  await p.getByRole('button', { name: 'Setujui', exact: true }).click();
  await p.waitForTimeout(1000);
  await p.getByRole('button', { name: 'Ya, setujui' }).click();
  await p.waitForTimeout(3500);
  console.log('pimpinan menyetujui, Terapkan terlihat:', await tombol(p, 'Terapkan prioritas'));

  // Manajer menerapkan
  await m.reload();
  await m.waitForTimeout(1800);
  await m.getByRole('button', { name: 'Terapkan prioritas' }).click();
  await shot(m, '69-evaluasi-terapkan-konfirmasi', 1200);
  console.log('dialog terapkan:', (await m.locator('.fi-modal-window').innerText()).replace(/\s+/g, ' ').slice(0, 400));
  await m.getByRole('button', { name: 'Ya, terapkan' }).click();
  await m.waitForTimeout(3500);
  console.log('diterapkan:', await m.getByText('Diterapkan').count() > 0);

  // Daftar order: label Prioritas Utama dan kunci, di layar 1366
  await m.setViewportSize({ width: 1366, height: 768 });
  await m.goto(BASE + '/admin/production-orders');
  await shot(m, '70-order-prioritas-utama', 2000);
  console.log('overflow daftar order 1366:', JSON.stringify(await m.evaluate(() => ({ sw: document.documentElement.scrollWidth, iw: window.innerWidth }))));
  console.log('label Prioritas Utama terlihat penuh di layar 1366:', await m.evaluate(() => Array.from(document.querySelectorAll('.fi-badge')).filter((b) => b.textContent.includes('Prioritas Utama') && b.getBoundingClientRect().right <= window.innerWidth).length));

  // Pasang kunci manual pada order Antri
  await m.setViewportSize({ width: 1600, height: 900 });
  await m.locator('.fi-ta-search-field input').fill('ORD-2026-0025');
  await m.waitForTimeout(3500);
  const barisOrder = m.locator('table tbody tr').filter({ hasText: 'ORD-2026-0025' }).first();
  await barisOrder.locator('button').first().click();
  await m.waitForTimeout(600);
  await m.getByRole('menuitem', { name: 'Pasang kunci' }).click();
  await m.waitForTimeout(1000);
  await m.getByLabel('Alasan penguncian').fill('Pelanggan besar, harus selesai sebelum akhir bulan');
  await shot(m, '71-order-pasang-kunci', 700);
  await m.getByRole('button', { name: 'Pasang kunci', exact: true }).last().click();
  await m.waitForTimeout(3500);
  console.log('setelah kunci:', (await m.locator('table tbody tr').filter({ hasText: 'ORD-2026-0025' }).first().innerHTML()).includes('lock') ? 'ikon kunci ada' : 'ikon kunci TIDAK ada');

  // Perhitungan kedua, lalu grafik tren dan bandingkan
  await hitung(m);
  await m.goto(BASE + '/admin/production-orders');
  await m.waitForTimeout(1500);
  await m.locator('.fi-ta-search-field input').fill('ORD-2026-0025');
  await m.waitForTimeout(3000);
  await m.locator('table tbody tr').filter({ hasText: 'ORD-2026-0025' }).first().click();
  await m.waitForURL(/\/admin\/production-orders\/\d+$/);
  await m.setViewportSize({ width: 1600, height: 1950 });
  await shot(m, '72-order-tren-peringkat', 4000);

  await m.goto(BASE + '/admin/evaluations');
  await m.waitForTimeout(1500);
  const idEvaluasi = (await m.locator('table tbody tr').allInnerTexts()).length;
  console.log('jumlah evaluasi:', idEvaluasi);
  await m.setViewportSize({ width: 1600, height: 1300 });
  await m.goto(BASE + '/admin/bandingkan-evaluasi?a=1&b=2');
  await shot(m, '73-evaluasi-bandingkan', 2500);

  // Halaman detail order bertambah bagian Tren peringkat dan tombol kunci, ambil ulang gambar Fase 4 yang memuatnya
  await m.setViewportSize({ width: 1600, height: 1750 });
  await m.goto(BASE + '/admin/production-orders/18');
  await shot(m, '34-order-penilaian', 3500);
  await m.goto(BASE + '/admin/production-orders/5');
  await shot(m, '35-order-riwayat-nilai', 3500);

  // Semua halaman baru di layar 1366
  await m.setViewportSize({ width: 1366, height: 768 });
  for (const alamat of ['/admin/evaluations', '/admin/evaluations/1', '/admin/evaluations/2', '/admin/bandingkan-evaluasi?a=1&b=2', '/admin/jalankan-perhitungan']) {
    await m.goto(BASE + alamat);
    await m.waitForTimeout(2500);
    console.log('1366', alamat, JSON.stringify(await m.evaluate(() => ({ sw: document.documentElement.scrollWidth, iw: window.innerWidth }))));
  }
  await m.goto(BASE + '/admin/evaluations/2');
  await m.waitForTimeout(2500);
  await shot(m, '75-evaluasi-detail-1366', 500);

  // Supervisor dan Admin: hanya melihat
  for (const [nama, email] of [['supervisor', 'supervisor@spk.test'], ['admin', 'admin@spk.test']]) {
    const { context, page } = await masuk(browser, email, { width: 1600, height: 1000 });
    const daftar = await page.goto(BASE + '/admin/evaluations');
    const detail = await page.goto(BASE + '/admin/evaluations/2');
    await page.waitForTimeout(2000);
    const teks = await page.evaluate(() => document.body.innerText);
    console.log(nama, '| daftar', daftar.status(), '| detail', detail.status(),
      '| tombol alur', (await tombol(page, 'Ajukan untuk disetujui')) + (await tombol(page, 'Setujui')) + (await tombol(page, 'Terapkan prioritas')) + (await tombol(page, 'Batalkan evaluasi')),
      '| ikon sesuaikan', await page.getByRole('button', { name: 'Sesuaikan peringkat' }).count(),
      '| Rp di halaman', /Rp\s?\d/.test(teks));
    if (nama === 'supervisor') {
      await page.getByRole('tab', { name: 'Data nilai asli' }).click();
      await page.waitForTimeout(800);
      const teksTab = await page.evaluate(() => document.body.innerText);
      console.log('supervisor tab Data nilai asli: Rp?', /Rp\s?\d/.test(teksTab), '| catatan C6:', teksTab.includes('Kriteria nilai order tidak ditampilkan'));
      await shot(page, '74-evaluasi-supervisor-tab-nilai', 800);
      await page.goto(BASE + '/admin/production-orders');
      await page.waitForTimeout(1500);
      await page.locator('table tbody tr').first().locator('button').first().click();
      await page.waitForTimeout(500);
      console.log('supervisor menu aksi order:', (await page.getByRole('menuitem').allInnerTexts()).join(' | '));
    }
    await context.close();
  }

  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 700)); process.exit(1); });
