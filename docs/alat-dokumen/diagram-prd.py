# Menggambar lima diagram PRD DSS (hitam putih) untuk disisipkan ke docx.
import os

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyArrowPatch, FancyBboxPatch, Ellipse, Polygon

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'gambar-prd')
os.makedirs(OUT, exist_ok=True)

INK = '#1a1a1a'
GRAY = '#6b6b6b'
FILL = '#f2f2f2'
WHITE = '#ffffff'
plt.rcParams['font.family'] = 'DejaVu Sans'


def box(ax, cx, cy, w, h, text, title=None, fill=WHITE, fs=9, align='center'):
    ax.add_patch(FancyBboxPatch((cx - w / 2, cy - h / 2), w, h, boxstyle='round,pad=0.02,rounding_size=0.06',
                                linewidth=1.1, edgecolor=INK, facecolor=fill, zorder=2))
    if title:
        ax.plot([cx - w / 2, cx + w / 2], [cy + h / 2 - 0.34, cy + h / 2 - 0.34], color=INK, lw=0.9, zorder=3)
        ax.text(cx, cy + h / 2 - 0.17, title, ha='center', va='center', fontsize=fs + 0.5, fontweight='bold', color=INK, zorder=3)
        ax.text(cx, cy + h / 2 - 0.34 - (h - 0.34) / 2, text, ha=('left' if align == 'left' else 'center'),
                va='center', fontsize=fs, color=INK, zorder=3,
                x_=None) if False else ax.text(cx if align == 'center' else cx - w / 2 + 0.12, cy + h / 2 - 0.34 - (h - 0.34) / 2,
                                                text, ha=align, va='center', fontsize=fs, color=INK, zorder=3)
    else:
        ax.text(cx if align == 'center' else cx - w / 2 + 0.12, cy, text, ha=align, va='center', fontsize=fs, color=INK, zorder=3)


def oval(ax, cx, cy, w, h, text, fs=9, fill=WHITE):
    ax.add_patch(Ellipse((cx, cy), w, h, linewidth=1.1, edgecolor=INK, facecolor=fill, zorder=2))
    ax.text(cx, cy, text, ha='center', va='center', fontsize=fs, color=INK, zorder=3)


def diamond(ax, cx, cy, w, h, text, fs=8.5):
    ax.add_patch(Polygon([(cx, cy + h / 2), (cx + w / 2, cy), (cx, cy - h / 2), (cx - w / 2, cy)],
                         closed=True, linewidth=1.1, edgecolor=INK, facecolor=FILL, zorder=2))
    ax.text(cx, cy, text, ha='center', va='center', fontsize=fs, color=INK, zorder=3)


def arrow(ax, x1, y1, x2, y2, label=None, style='-|>', color=INK, ls='-', rad=0.0, fs=8):
    ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2), arrowstyle=style, mutation_scale=12,
                                 linewidth=1.0, color=color, linestyle=ls, zorder=1,
                                 connectionstyle=f'arc3,rad={rad}'))
    if label:
        ax.text((x1 + x2) / 2, (y1 + y2) / 2, label, ha='center', va='center', fontsize=fs,
                color=color, zorder=4, bbox=dict(boxstyle='round,pad=0.12', fc=WHITE, ec='none'))


def line(ax, x1, y1, x2, y2, label=None, color=GRAY, fs=8):
    ax.plot([x1, x2], [y1, y2], color=color, lw=0.9, zorder=1)
    if label:
        ax.text((x1 + x2) / 2, (y1 + y2) / 2, label, ha='center', va='center', fontsize=fs,
                color=INK, zorder=4, bbox=dict(boxstyle='round,pad=0.1', fc=WHITE, ec='none'))


def actor(ax, cx, cy, label):
    ax.add_patch(plt.Circle((cx, cy + 0.42), 0.12, fill=False, ec=INK, lw=1.1, zorder=3))
    ax.plot([cx, cx], [cy + 0.3, cy - 0.12], color=INK, lw=1.1, zorder=3)
    ax.plot([cx - 0.2, cx + 0.2], [cy + 0.16, cy + 0.16], color=INK, lw=1.1, zorder=3)
    ax.plot([cx, cx - 0.16], [cy - 0.12, cy - 0.4], color=INK, lw=1.1, zorder=3)
    ax.plot([cx, cx + 0.16], [cy - 0.12, cy - 0.4], color=INK, lw=1.1, zorder=3)
    ax.text(cx, cy - 0.56, label, ha='center', va='center', fontsize=9, fontweight='bold', color=INK, zorder=3)


def save(fig, name):
    fig.savefig(os.path.join(OUT, name), dpi=200, bbox_inches='tight', facecolor=WHITE, pad_inches=0.15)
    plt.close(fig)


def new(w, h):
    fig, ax = plt.subplots(figsize=(w, h))
    ax.set_xlim(0, 10)
    ax.set_aspect('equal')
    ax.axis('off')
    return fig, ax


# ---------------------------------------------------------------- 1. Flowchart
def flowchart():
    fig, ax = plt.subplots(figsize=(7.8, 15))
    ax.set_xlim(0, 10)
    ax.set_ylim(0, 29)
    ax.set_aspect('auto')
    ax.axis('off')
    xc = 4.2
    bw, bh, dw, dh = 4.6, 1.15, 3.6, 1.35
    steps = [
        ('o', 'Mulai'),
        ('p', 'Input atau impor data order'),
        ('p', 'Lengkapi nilai penilaian order\n(otomatis: C1, C2, C6, C7; manual: C3, C4, C5)'),
        ('d', 'Total bobot = 1 ?'),
        ('d', 'Ada order aktif ?'),
        ('p', 'Konversi nilai asli ke skala 1..5'),
        ('p', 'Normalisasi: r = x / maks kolom'),
        ('p', 'Hitung skor V = jumlah (bobot x r)'),
        ('p', 'Urutkan skor, lalu susun peringkat akhir:\nterkunci, eskalasi, biasa'),
        ('p', 'Simpan sebagai Evaluasi (tidak dapat diubah)'),
        ('d', 'Penyesuaian manual ?'),
        ('d', 'Persetujuan Pimpinan wajib ?'),
        ('p', 'Terapkan prioritas:\nN order Antri teratas = Prioritas Utama'),
        ('p', 'Tampil di dasbor, cetak / unduh laporan'),
        ('o', 'Selesai'),
    ]
    gap = 1.95
    pos = {}
    y = 28
    for i, (kind, text) in enumerate(steps):
        pos[i] = y
        if kind == 'o':
            oval(ax, xc, y, 2.4, 1.0, text)
        elif kind == 'p':
            box(ax, xc, y, bw, bh, text, fs=8.5)
        else:
            diamond(ax, xc, y, dw, dh, text, fs=8.5)
        y -= gap

    def half(i):
        return 0.5 if steps[i][0] == 'o' else (bh / 2 if steps[i][0] == 'p' else dh / 2)

    for i in range(len(steps) - 1):
        arrow(ax, xc, pos[i] - half(i), xc, pos[i + 1] + half(i + 1))
    # label ya pada jalur utama setelah dua diamond
    ax.text(xc + 0.33, (pos[3] - half(3) + pos[4] + half(4)) / 2, 'ya', fontsize=8, ha='left', color=INK)
    ax.text(xc + 0.33, (pos[4] - half(4) + pos[5] + half(5)) / 2, 'ya', fontsize=8, ha='left', color=INK)
    ax.text(xc + 0.33, (pos[10] - half(10) + pos[11] + half(11)) / 2, 'tidak', fontsize=8, ha='left', color=INK)
    ax.text(xc + 0.33, (pos[11] - half(11) + pos[12] + half(12)) / 2, 'tidak', fontsize=8, ha='left', color=INK)
    # cabang: bobot tidak -> perbaiki -> loop ke Lengkapi
    box(ax, 8.4, pos[3], 2.6, 0.95, 'Perbaiki /\nnormalisasi bobot', fs=8)
    arrow(ax, xc + dw / 2, pos[3], 8.4 - 1.3, pos[3], 'tidak')
    arrow(ax, 8.4, pos[3] + 0.48, 8.4, pos[2], color=GRAY)
    arrow(ax, 8.4, pos[2], xc + bw / 2, pos[2], color=GRAY)
    # order aktif tidak -> berhenti
    box(ax, 8.4, pos[4], 2.5, 0.9, 'Berhenti, beri pesan', fs=8)
    arrow(ax, xc + dw / 2, pos[4], 8.4 - 1.25, pos[4], 'tidak')
    # penyesuaian manual ya -> ubah peringkat, kembali ke persetujuan
    box(ax, 8.4, pos[10], 2.6, 0.95, 'Ubah peringkat\n+ alasan', fs=8)
    arrow(ax, xc + dw / 2, pos[10], 8.4 - 1.3, pos[10], 'ya')
    # persetujuan ya -> pimpinan setujui
    box(ax, 8.4, pos[11], 2.6, 0.95, 'Pimpinan setujui\natau tolak', fs=8)
    arrow(ax, xc + dw / 2, pos[11], 8.4 - 1.3, pos[11], 'ya')
    save(fig, 'flowchart.png')


# ------------------------------------------------------------- 2. Deployment
def deployment():
    fig, ax = new(7.6, 8.2)
    ax.set_ylim(0, 13)
    box(ax, 5, 12, 6.4, 1.1, 'PC / Laptop / Ponsel  +  Peramban\n(Chrome, Edge, Firefox)', title='Pengguna', fs=8.5)
    arrow(ax, 5, 11.45, 5, 10.7, 'HTTPS (443)')
    # server boundary
    ax.add_patch(FancyBboxPatch((0.6, 1.1), 8.8, 9.3, boxstyle='round,pad=0.02,rounding_size=0.1',
                                linewidth=1.3, edgecolor=INK, facecolor='#fbfbfb', zorder=0))
    ax.text(5, 10.05, 'Server Hosting cPanel', ha='center', va='center', fontsize=10, fontweight='bold', color=INK)
    box(ax, 5, 8.6, 7.2, 1.15, 'public_html/  ->  index.php, aset publik', title='Web server (LiteSpeed / Apache)', fs=8.5)
    arrow(ax, 5, 8.0, 5, 7.25, 'memanggil')
    box(ax, 5, 6.1, 7.2, 1.3, 'Aplikasi Laravel + Filament\nfolder aplikasi dan .env di luar folder publik', title='PHP 8.3', fs=8.5)
    arrow(ax, 3.4, 5.45, 3.4, 4.35, 'koneksi lokal')
    box(ax, 3.4, 3.7, 3.8, 1.1, 'MySQL / MariaDB', title='Basis data', fs=8.5)
    box(ax, 7.0, 3.7, 3.4, 1.3, 'Setiap hari 06.00\nphp artisan schedule:run', title='Cron Job cPanel', fs=8)
    arrow(ax, 7.0, 4.35, 7.0, 5.45, 'evaluasi terjadwal', color=GRAY)
    ax.text(5, 1.7, 'APP_DEBUG mati  |  migrasi lewat Cron  |  tanpa akses terminal', ha='center', va='center',
            fontsize=8, style='italic', color=GRAY)
    save(fig, 'deployment.png')


# ------------------------------------------------------------- 3. Use case
def usecase():
    fig, ax = plt.subplots(figsize=(8.4, 11))
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 24)
    ax.set_aspect('auto')
    ax.axis('off')
    ax.add_patch(FancyBboxPatch((3.1, 0.6), 5.8, 22.8, boxstyle='round,pad=0.02', linewidth=1.3,
                                edgecolor=INK, facecolor='#fcfcfc', zorder=0))
    ax.text(6.0, 22.9, 'SPK Prioritas Produksi', ha='center', fontsize=10.5, fontweight='bold', color=INK)
    uc = [
        ('UC01 Masuk', 'ALL'), ('UC02 Ubah Profil', 'ALL'),
        ('UC03 Kelola Pengguna', 'A'), ('UC20 Lihat Log', 'A'), ('UC21 Pengaturan', 'A'),
        ('UC04 Kelola Pelanggan', 'M'), ('UC05 Kelola Produk', 'M'), ('UC06 Kelola Order', 'M'),
        ('UC08 Impor Order', 'M'), ('UC09 Kriteria & Bobot', 'M'), ('UC10 Skala Nilai', 'M'),
        ('UC11 Isi Penilaian', 'M'), ('UC12 Jalankan SAW', 'M'), ('UC14 Sesuaikan Peringkat', 'M'),
        ('UC16 Terapkan Prioritas', 'M'), ('UC17 Bandingkan Evaluasi', 'M'),
        ('UC23 Kunci / Lepas Kunci', 'M'), ('UC24 Pantau Order Tertahan', 'M'),
        ('UC07 Perbarui Progres', 'S'), ('UC13 Tinjau Hasil', 'S'), ('UC18 Lihat Dasbor', 'S'),
        ('UC19 Laporan', 'S'), ('UC15 Setujui / Tolak', 'P'), ('UC22 Terima Notifikasi', 'P'),
    ]
    n = len(uc)
    top, bot = 22.0, 1.4
    ys = [top - (top - bot) * i / (n - 1) for i in range(n)]
    pos = {}
    for (name, _), yy in zip(uc, ys):
        oval(ax, 6.0, yy, 3.3, 0.82, name, fs=7.6)
        pos[name] = yy
    actors = {
        'A': ('Admin', 1.2, 20.5), 'M': ('Manajer\nProduksi', 1.2, 12), 'S': ('Supervisor', 1.2, 4.5),
        'P': ('Pimpinan', 10.8, 10), 'SYS': ('Sistem\n(penjadwal)', 10.8, 3.0),
    }
    apos = {}
    for k, (lbl, x, yy) in actors.items():
        actor(ax, x, yy, lbl)
        apos[k] = (x, yy)
    side = {'A': 'L', 'M': 'L', 'S': 'L', 'P': 'R', 'SYS': 'R'}
    for name, owner in uc:
        yy = pos[name]
        targets = []
        if owner == 'ALL':
            targets = ['M']
        else:
            targets = [owner]
        for t in targets:
            ax_, ay = apos[t]
            ex = 6.0 - 1.65 if side[t] == 'L' else 6.0 + 1.65
            line(ax, ax_ + (0.25 if side[t] == 'L' else -0.25), ay, ex, yy)
    # extra: UC12 to Sistem, UC15/UC09/UC14/UC16/UC13/UC18/UC19/UC22 to Pimpinan
    line(ax, apos['S'][0] + 0.25, apos['S'][1], 6.0 - 1.65, pos['UC24 Pantau Order Tertahan'], color='#b0b0b0')
    for name in ['UC12 Jalankan SAW']:
        line(ax, apos['SYS'][0] - 0.25, apos['SYS'][1], 6.0 + 1.65, pos[name], color='#b0b0b0')
    for name in ['UC09 Kriteria & Bobot', 'UC14 Sesuaikan Peringkat', 'UC16 Terapkan Prioritas',
                 'UC13 Tinjau Hasil', 'UC18 Lihat Dasbor', 'UC19 Laporan',
                 'UC23 Kunci / Lepas Kunci', 'UC24 Pantau Order Tertahan']:
        line(ax, apos['P'][0] - 0.25, apos['P'][1], 6.0 + 1.65, pos[name], color='#b0b0b0')
    save(fig, 'usecase.png')


# ------------------------------------------------------------------ 4. ERD
def erd():
    fig, ax = new(10.5, 8.6)
    ax.set_xlim(0, 15)
    ax.set_ylim(0, 12)

    def ent(cx, cy, title, attrs, w=3.0, fill=WHITE):
        h = 0.5 + 0.34 * len(attrs) + 0.5
        box(ax, cx, cy, w, h, '\n'.join(attrs), title=title, fill=fill, fs=7.6, align='left')
        return (cx, cy, w, h)

    pel = ent(2.3, 10.4, 'PELANGGAN', ['id (PK)', 'kode', 'nama', 'urgensi', 'aktif'])
    pro = ent(12.7, 10.4, 'PRODUK', ['id (PK)', 'kode', 'nama', 'harga', 'aktif'])
    order = ent(7.5, 8.2, 'ORDER PRODUKSI', ['id (PK)', 'nomor', 'jumlah', 'tenggat', 'status', 'prioritas'], w=3.4, fill=FILL)
    krit = ent(2.1, 5.0, 'KRITERIA', ['id (PK)', 'kode', 'nama', 'arah', 'bobot'])
    skala = ent(2.1, 1.6, 'SKALA KRITERIA', ['id (PK)', 'skor 1..5', 'batas bawah', 'batas atas'])
    nil = ent(7.5, 4.4, 'PENILAIAN', ['id (PK)', 'nilai asli', 'sumber', 'waktu'], fill=FILL)
    ev = ent(12.9, 5.0, 'EVALUASI', ['id (PK)', 'nama', 'waktu', 'pemicu', 'status'])
    item = ent(12.9, 1.5, 'ITEM EVALUASI', ['id (PK)', 'skor', 'peringkat', 'peringatan'], fill=FILL)
    peng = ent(7.5, 1.3, 'PENGGUNA', ['id (PK)', 'nama', 'peran', 'aktif'])

    def card(x, y, t):
        ax.text(x, y, t, fontsize=8.5, ha='center', va='center', color=INK,
                bbox=dict(boxstyle='round,pad=0.08', fc=WHITE, ec='none'), zorder=5)

    def hh(e):
        return e[3] / 2

    def hw(e):
        return e[2] / 2
    # PELANGGAN 1 -- M ORDER
    line(ax, pel[0] + hw(pel), pel[1] - 0.8, order[0] - hw(order), order[1] + 0.9, 'memesan', color=GRAY)
    card(pel[0] + hw(pel) + 0.2, pel[1] - 0.9, '1'); card(order[0] - hw(order) - 0.2, order[1] + 1.0, 'M')
    # PRODUK 1 -- M ORDER
    line(ax, pro[0] - hw(pro), pro[1] - 0.8, order[0] + hw(order), order[1] + 0.9, 'memesan', color=GRAY)
    card(pro[0] - hw(pro) - 0.2, pro[1] - 0.9, '1'); card(order[0] + hw(order) + 0.2, order[1] + 1.0, 'M')
    # ORDER 1 -- M PENILAIAN
    line(ax, order[0], order[1] - hh(order), nil[0], nil[1] + hh(nil), color=GRAY)
    card(order[0] + 0.25, order[1] - hh(order) - 0.25, '1'); card(nil[0] + 0.25, nil[1] + hh(nil) + 0.25, 'M')
    # KRITERIA 1 -- M PENILAIAN
    line(ax, krit[0] + hw(krit), krit[1] - 0.3, nil[0] - hw(nil), nil[1] + 0.3, color=GRAY)
    card(krit[0] + hw(krit) + 0.2, krit[1] - 0.2, '1'); card(nil[0] - hw(nil) - 0.2, nil[1] + 0.4, 'M')
    # KRITERIA 1 -- M SKALA
    line(ax, krit[0], krit[1] - hh(krit), skala[0], skala[1] + hh(skala), color=GRAY)
    card(krit[0] + 0.25, krit[1] - hh(krit) - 0.25, '1'); card(skala[0] + 0.25, skala[1] + hh(skala) + 0.25, 'M')
    # EVALUASI 1 -- M ITEM EVALUASI
    line(ax, ev[0], ev[1] - hh(ev), item[0], item[1] + hh(item), color=GRAY)
    card(ev[0] + 0.25, ev[1] - hh(ev) - 0.25, '1'); card(item[0] + 0.25, item[1] + hh(item) + 0.25, 'M')
    # ORDER 1 -- M ITEM EVALUASI
    line(ax, order[0] + hw(order), order[1] - 0.4, item[0] - hw(item), item[1] + 0.4, color=GRAY)
    card(order[0] + hw(order) + 0.2, order[1] - 0.6, '1'); card(item[0] - hw(item) - 0.2, item[1] + 0.5, 'M')
    # PENGGUNA 1 -- M PENILAIAN (memperbarui)
    line(ax, peng[0], peng[1] + hh(peng), nil[0], nil[1] - hh(nil), 'memperbarui', color='#9a9a9a')
    card(peng[0] + 0.25, peng[1] + hh(peng) + 0.2, '1'); card(nil[0] + 0.25, nil[1] - hh(nil) - 0.2, 'M')
    save(fig, 'erd.png')


# ------------------------------------------------------------------ 5. LRS
def lrs():
    fig, ax = new(11, 9)
    ax.set_xlim(0, 16)
    ax.set_ylim(0, 13)

    def tbl(cx, cy, title, rows, w=3.2, fill=WHITE):
        h = 0.5 + 0.32 * len(rows) + 0.4
        box(ax, cx, cy, w, h, '\n'.join(rows), title=title, fill=fill, fs=7.2, align='left')
        return (cx, cy, w, h)

    cust = tbl(2.2, 11.3, 'customers', ['PK id', 'code', 'name', 'urgency_level'])
    prod = tbl(13.8, 11.3, 'products', ['PK id', 'code', 'name', 'unit_price'])
    order = tbl(8.0, 9.0, 'production_orders', ['PK id', 'number', 'FK customer_id', 'FK product_id', 'deadline_date', 'status', 'priority', 'FK created_by'], w=3.8, fill=FILL)
    assess = tbl(2.4, 5.6, 'assessments', ['PK id', 'FK production_order_id', 'FK criterion_id', 'raw_value'], w=3.6, fill=FILL)
    hist = tbl(2.4, 1.8, 'assessment_histories', ['PK id', 'FK assessment_id', 'old_value', 'new_value'], w=3.6)
    crit = tbl(6.6, 2.0, 'criteria', ['PK id', 'code', 'direction', 'weight'])
    scale = tbl(6.6, 5.6, 'criterion_scales', ['PK id', 'FK criterion_id', 'score', 'lower_bound', 'upper_bound'])
    item = tbl(13.6, 5.6, 'evaluation_items', ['PK id', 'FK evaluation_id', 'FK production_order_id', 'score', 'system_rank', 'final_rank'], w=3.8, fill=FILL)
    ev = tbl(13.6, 1.8, 'evaluations', ['PK id', 'name', 'status', 'FK evaluated_by', 'FK approved_by'])

    def rel(a, b, lx='1', mx='M'):
        line(ax, a[0], a[1], b[0], b[1], color=GRAY)
    line(ax, cust[0] + 0.5, cust[1] - 1.0, order[0] - 1.2, order[1] + 1.6, color=GRAY)
    ax.text(cust[0] + 0.7, cust[1] - 1.2, '1', fontsize=8); ax.text(order[0] - 1.4, order[1] + 1.5, 'M', fontsize=8)
    line(ax, prod[0] - 0.5, prod[1] - 1.0, order[0] + 1.2, order[1] + 1.6, color=GRAY)
    ax.text(prod[0] - 0.7, prod[1] - 1.2, '1', fontsize=8); ax.text(order[0] + 1.4, order[1] + 1.5, 'M', fontsize=8)
    line(ax, order[0] - 1.9, order[1] - 1.73, assess[0] + 1.6, assess[1] + 1.1, color=GRAY)
    ax.text(order[0] - 2.1, order[1] - 2.0, '1', fontsize=8); ax.text(assess[0] + 1.75, assess[1] + 1.0, 'M', fontsize=8)
    line(ax, assess[0], assess[1] - 1.1, hist[0], hist[1] + 1.09, color=GRAY)
    ax.text(assess[0] + 0.2, assess[1] - 1.3, '1', fontsize=8); ax.text(hist[0] + 0.25, hist[1] + 1.35, 'M', fontsize=8)
    line(ax, crit[0], crit[1] + 1.0, scale[0], scale[1] - 1.2, color=GRAY)
    ax.text(crit[0] + 0.2, crit[1] + 1.1, '1', fontsize=8); ax.text(scale[0] + 0.2, scale[1] - 1.1, 'M', fontsize=8)
    line(ax, crit[0] - 0.5, crit[1] + 0.9, assess[0], assess[1] - 1.1, color='#b0b0b0')
    ax.text(crit[0] - 0.8, crit[1] + 1.0, '1', fontsize=8); ax.text(assess[0] - 0.2, assess[1] - 1.2, 'M', fontsize=8)
    line(ax, order[0] + 1.9, order[1] - 1.73, item[0] - 1.9, item[1] + 1.1, color=GRAY)
    ax.text(order[0] + 2.1, order[1] - 2.0, '1', fontsize=8); ax.text(item[0] - 1.75, item[1] + 1.35, 'M', fontsize=8)
    line(ax, ev[0], ev[1] + 1.1, item[0], item[1] - 1.5, color=GRAY)
    ax.text(ev[0] + 0.2, ev[1] + 1.2, '1', fontsize=8); ax.text(item[0] + 0.2, item[1] - 1.4, 'M', fontsize=8)
    save(fig, 'lrs.png')


flowchart()
deployment()
usecase()
erd()
lrs()
print('selesai:', sorted(os.listdir(OUT)))
