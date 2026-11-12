// Tangkapan layar dan uji peramban untuk Fase 9 (pengaturan, log aktivitas, notifikasi).
// Jalankan sesudah:
//   php artisan migrate:fresh --seed
//   php artisan evaluation:run-scheduled --force      (evaluasi terjadwal, mengirim N5, N6, dan N9)
//   php artisan tinker --execute="app(App\Services\Evaluation\EvaluationWorkflow::class)->submit(App\Models\Evaluation::latest('id')->first(), App\Models\User::where('email','manajer@spk.test')->first());"
//                                                     (pengajuan, mengirim N1 ke Pimpinan)
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

async function lebar(page, label) {
  const r = await page.evaluate(() => ({ s: document.documentElement.scrollWidth, c: document.documentElement.clientWidth }));
  console.log('lebar', label, r.s, r.c, r.s > r.c ? 'MELUAP' : 'pas');
}

async function menu(page) {
  return page.locator('.fi-sidebar-item-label').allTextContents().then((t) => t.map((s) => s.trim()));
}

async function bukaLonceng(page) {
  const tombol = page.locator('.fi-topbar-database-notifications-btn, [x-on\\:click*="database-notifications"]').first();
  if (await tombol.count()) {
    await tombol.click();
  } else {
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'database-notifications' } })));
  }
  await page.waitForTimeout(2000);
}

(async () => {
  const browser = await chromium.launch(opsiPeramban());

  // Admin: halaman pengaturan lengkap
  const admin = await masuk(browser, 'admin@spk.test', { width: 1600, height: 2000 });
  const a = admin.page;
  console.log('menu admin:', (await menu(a)).join(' | '));
  await a.goto(BASE + '/admin/pengaturan');
  await shot(a, '100-pengaturan-admin', 2500);
  await lebar(a, 'pengaturan 1600');

  // Simpan tanpa perubahan
  await a.getByRole('button', { name: 'Simpan pengaturan' }).click();
  await a.waitForTimeout(1500);
  console.log('simpan tanpa ubah:', await a.getByText('Tidak ada perubahan untuk disimpan').count());

  // Aturan yang salah: tertahan tidak boleh sama atau lebih lama dari batas tunggu
  await a.setViewportSize({ width: 1600, height: 1100 });
  await a.goto(BASE + '/admin/pengaturan');
  await a.waitForTimeout(2000);
  await a.locator('input[id$="held_min_days"]').fill('7');
  await a.getByRole('button', { name: 'Simpan pengaturan' }).click();
  await a.waitForTimeout(1500);
  await a.locator('input[id$="held_min_days"]').scrollIntoViewIfNeeded();
  await shot(a, '101-pengaturan-salah', 800);

  // Perubahan yang benar: jumlah Prioritas Utama 5 -> 6
  await a.goto(BASE + '/admin/pengaturan');
  await a.waitForTimeout(2000);
  await a.locator('input[id$="top_n_priority"]').fill('6');
  await a.getByRole('button', { name: 'Simpan pengaturan' }).click();
  await a.waitForTimeout(1500);
  console.log('simpan berhasil:', await a.getByText('Pengaturan disimpan').count());

  // Log aktivitas
  await a.goto(BASE + '/admin/log-aktivitas');
  await shot(a, '102-log-aktivitas', 2500);
  await lebar(a, 'log 1600');
  await a.goto(BASE + '/admin/log-aktivitas?filters[action][values][0]=setting.updated');
  await shot(a, '103-log-pengaturan', 2500);
  console.log('baris log pengaturan:', await a.locator('table tbody tr').count());

  // Kembalikan nilai awal
  await a.goto(BASE + '/admin/pengaturan');
  await a.waitForTimeout(2000);
  await a.locator('input[id$="top_n_priority"]').fill('5');
  await a.getByRole('button', { name: 'Simpan pengaturan' }).click();
  await a.waitForTimeout(1500);
  await admin.context.close();

  // Pimpinan: pengaturan terbatas dan notifikasi N1
  const pimpinan = await masuk(browser, 'pimpinan@spk.test', { width: 1600, height: 1100 });
  const p = pimpinan.page;
  console.log('menu pimpinan:', (await menu(p)).join(' | '));
  await p.goto(BASE + '/admin/pengaturan');
  await p.waitForTimeout(2500);
  console.log('pimpinan - bagian:', (await p.locator('.fi-section-header-heading').allTextContents()).map((s) => s.trim()).join(' | '));
  await shot(p, '104-pengaturan-pimpinan', 300);
  await p.goto(BASE + '/admin');
  await p.waitForTimeout(2500);
  await bukaLonceng(p);
  await shot(p, '105-notifikasi-pimpinan', 500);
  await pimpinan.context.close();

  // Manajer: notifikasi hasil hitung terjadwal, tanpa menu pengaturan dan log
  const manajer = await masuk(browser, 'manajer@spk.test', { width: 1600, height: 1100 });
  const m = manajer.page;
  console.log('menu manajer:', (await menu(m)).join(' | '));
  await bukaLonceng(m);
  await shot(m, '106-notifikasi-manajer', 500);
  for (const url of ['/admin/pengaturan', '/admin/log-aktivitas']) {
    const r = await m.goto(BASE + url);
    console.log('manajer buka', url, '->', r.status());
  }
  await manajer.context.close();

  // Supervisor: tidak bisa membuka pengaturan dan log
  const supervisor = await masuk(browser, 'supervisor@spk.test', { width: 1366, height: 900 });
  const s = supervisor.page;
  console.log('menu supervisor:', (await menu(s)).join(' | '));
  for (const url of ['/admin/pengaturan', '/admin/log-aktivitas']) {
    const r = await s.goto(BASE + url);
    console.log('supervisor buka', url, '->', r.status());
  }
  await supervisor.context.close();

  await browser.close();
})().catch((e) => { console.error('GAGAL', e.message.slice(0, 700)); process.exit(1); });
