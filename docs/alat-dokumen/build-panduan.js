// Membangun docx dokumentasi (panduan penggunaan dan pengujian) dari berkas markdown; turunan build-prd.js: judul, paragraf, tabel, daftar,
// dan lima blok kode ASCII diganti gambar diagram. Jalankan dua kali untuk nomor halaman.
const fs = require('fs');
const path = require('path');
const {
    Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell,
    WidthType, BorderStyle, ShadingType, ImageRun, Footer, Header, PageNumber, NumberFormat,
    LevelFormat, Tab, TabStopType, VerticalAlign, PageBreak,
} = require('docx');

const DIR = __dirname;
const ROOT = path.resolve(DIR, '..', '..');
const IMG = process.argv[4] || path.join(DIR, 'gambar-panduan');
const MD = process.argv[3] || path.join(ROOT, 'Panduan-Penggunaan-dan-Pengujian-SPK.md');
const OUT = process.argv[2] || path.join(DIR, 'doc.docx');
const PAGES = fs.existsSync(path.join(DIR, 'pages-doc.json')) ? JSON.parse(fs.readFileSync(path.join(DIR, 'pages-doc.json'), 'utf8')) : {};

const FONT = 'Calibri';
const MONO = 'Consolas';
const INK = '1A1A1A';
const MUTED = '595959';
const RULE = 'BFBFBF';
const HEAD_FILL = 'E7E7E7';
const CONTENT_WIDTH = 9072;

const DIAGRAMS = [
    ['flowchart.png', 'Diagram Alur Sistem'],
    ['deployment.png', 'Arsitektur Deployment'],
    ['erd.png', 'Entity Relationship Diagram (ERD)'],
    ['lrs.png', 'Logical Record Structure (LRS)'],
    ['usecase.png', 'Use Case Diagram'],
];

function pngSize(file) {
    const b = fs.readFileSync(file);
    return { w: b.readUInt32BE(16), h: b.readUInt32BE(20) };
}

/* Markup sebaris: **tebal** dan `kode`. */
function runs(text, o = {}) {
    const size = o.size || 24;
    const color = o.color || INK;
    const out = [];
    const re = /(\*\*[^*]+\*\*|`[^`]+`)/g;
    let last = 0, m;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) out.push(new TextRun({ text: text.slice(last, m.index), size, color, bold: o.bold, italics: o.italics }));
        const t = m[0];
        if (t.startsWith('**')) out.push(new TextRun({ text: t.slice(2, -2), bold: true, size, color }));
        else out.push(new TextRun({ text: t.slice(1, -1), font: MONO, size: size - 2, color }));
        last = m.index + t.length;
    }
    if (last < text.length) out.push(new TextRun({ text: text.slice(last), size, color, bold: o.bold, italics: o.italics }));
    return out.length ? out : [new TextRun({ text: '', size })];
}

const plain = (t) => t.replace(/\*\*([^*]+)\*\*/g, '$1').replace(/`([^`]+)`/g, '$1');

const headings = [];
const figures = [];
let figNo = 0;

let firstChapter = true;
function h1(text) {
    headings.push({ level: 1, text });
    const p = new Paragraph({
        heading: HeadingLevel.HEADING_1,
        children: [new TextRun({ text, size: 30, bold: true, color: INK })],
        pageBreakBefore: firstChapter, spacing: { before: firstChapter ? 0 : 400, after: 200 },
        border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: INK, space: 6 } },
        keepNext: true,
    });
    firstChapter = false;
    return p;
}
function h2(text) {
    headings.push({ level: 2, text });
    return new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun({ text, size: 26, bold: true, color: INK })], spacing: { before: 240, after: 100 }, keepNext: true });
}
const para = (text) => new Paragraph({ children: runs(text), alignment: AlignmentType.JUSTIFIED, spacing: { after: 130, line: 300 } });

const numbering = [{ reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '\u2022', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 460, hanging: 260 } } } }] }];
let listCount = 0;
function numberRef(start = 1) {
    listCount += 1;
    const ref = `num-${listCount}`;
    numbering.push({ reference: ref, levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', start, alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 320 } } } }] });
    return ref;
}
const bullet = (text) => new Paragraph({ children: runs(text), numbering: { reference: 'bullets', level: 0 }, spacing: { after: 60, line: 282 } });
const numbered = (text, ref) => new Paragraph({ children: runs(text), numbering: { reference: ref, level: 0 }, spacing: { after: 60, line: 282 } });

const cellBorder = { style: BorderStyle.SINGLE, size: 4, color: RULE };
const borders = { top: cellBorder, bottom: cellBorder, left: cellBorder, right: cellBorder };
function cell(text, w, header) {
    return new TableCell({
        width: { size: w, type: WidthType.DXA }, borders,
        shading: header ? { fill: HEAD_FILL, type: ShadingType.CLEAR, color: 'auto' } : undefined,
        margins: { top: 58, bottom: 58, left: 95, right: 95 }, verticalAlign: VerticalAlign.CENTER,
        children: [new Paragraph({ children: runs(text, { size: 21, bold: header }), spacing: { after: 0, line: 252 } })],
    });
}
function makeTable(rows) {
    const cols = rows[0].length;
    const lens = Array(cols).fill(1);
    rows.forEach((r) => r.forEach((c, i) => { lens[i] = Math.max(lens[i], Math.min(plain(c).length, 46)); }));
    const total = lens.reduce((a, b) => a + b, 0);
    const minimum = 1050;
    const widths = lens.map((l) => Math.max(Math.round(l / total * CONTENT_WIDTH), minimum));
    const widest = widths.indexOf(Math.max(...widths));
    widths[widest] += CONTENT_WIDTH - widths.reduce((a, b) => a + b, 0);
    return new Table({
        width: { size: CONTENT_WIDTH, type: WidthType.DXA }, columnWidths: widths,
        rows: rows.map((r, ri) => new TableRow({ tableHeader: ri === 0, cantSplit: true, children: r.map((c, ci) => cell(c, widths[ci], ri === 0)) })),
    });
}

function figure(file, title) {
    figNo += 1;
    const label = `Gambar ${figNo}. ${title}`;
    figures.push(label);
    const { w, h } = pngSize(path.join(IMG, file));
    const maxW = 540, maxH = 640;
    const scale = Math.min(maxW / w, maxH / h);
    const iw = Math.round(w * scale), ih = Math.round(h * scale);
    const frameW = iw * 15 + 120;
    const edge = { style: BorderStyle.SINGLE, size: 6, color: RULE };
    const frame = new Table({
        width: { size: frameW, type: WidthType.DXA }, columnWidths: [frameW], alignment: AlignmentType.CENTER,
        rows: [new TableRow({ cantSplit: true, children: [new TableCell({
            width: { size: frameW, type: WidthType.DXA }, borders: { top: edge, bottom: edge, left: edge, right: edge },
            margins: { top: 40, bottom: 40, left: 60, right: 60 },
            children: [new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 0 }, keepNext: true, children: [new ImageRun({ type: 'png', data: fs.readFileSync(path.join(IMG, file)), transformation: { width: iw, height: ih } })] })],
        })] })],
    });
    return [
        frame,
        new Paragraph({ children: [new TextRun({ text: label, size: 20, italics: true, color: MUTED })], alignment: AlignmentType.CENTER, spacing: { before: 80, after: 220 } }),
    ];
}

function codeBlock(lines) {
    const edge = { style: BorderStyle.SINGLE, size: 4, color: RULE };
    return new Table({
        width: { size: CONTENT_WIDTH, type: WidthType.DXA }, columnWidths: [CONTENT_WIDTH],
        rows: [new TableRow({ cantSplit: true, children: [new TableCell({
            width: { size: CONTENT_WIDTH, type: WidthType.DXA }, borders: { top: edge, bottom: edge, left: edge, right: edge },
            shading: { fill: 'F2F2F2', type: ShadingType.CLEAR, color: 'auto' }, margins: { top: 80, bottom: 80, left: 140, right: 140 },
            children: lines.map((l) => new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: l, font: MONO, size: 21, color: INK })] })),
        })] })],
    });
}

/* ------------------------------------------------------- parser markdown */

const md = fs.readFileSync(MD, 'utf8').replace(/\r\n/g, '\n').split('\n');
const body = [];
let coverInfo = [];
let docTitle = 'PRD';
let i = 0;
let started = false;
let codeIndex = 0;

function parseTableAt(start) {
    const rows = [];
    let j = start;
    while (j < md.length && md[j].trim().startsWith('|')) {
        const cells = md[j].trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());
        rows.push(cells);
        j++;
    }
    const clean = rows.filter((r) => !r.every((c) => /^-+:?$/.test(c) || c === ''));
    return { rows: clean, next: j };
}

while (i < md.length) {
    let line = md[i];
    const t = line.trim();

    if (!started) {
        if (t.startsWith('# ')) { docTitle = t.slice(2).trim(); i++; continue; }
        if (t.startsWith('|')) { const { rows, next } = parseTableAt(i); coverInfo = rows; i = next; continue; }
        if (t.startsWith('## ')) started = true; else { i++; continue; }
    }

    if (t === '' || t === '---') { i++; continue; }

    if (t.startsWith('## ')) { body.push(h1(t.slice(3).trim())); i++; continue; }
    if (t.startsWith('### ')) { body.push(h2(t.slice(4).trim())); i++; continue; }
    if (t.startsWith('#### ')) { body.push(h2(t.slice(5).trim())); i++; continue; }

    if (t.startsWith('```')) {
        i++;
        const lines = [];
        while (i < md.length && !md[i].trim().startsWith('```')) { lines.push(md[i]); i++; }
        i++;
        body.push(codeBlock(lines));
        body.push(new Paragraph({ children: [], spacing: { after: 100 } }));
        continue;
    }

    const img = t.match(/^!\[(.+?)\]\((.+?)\)$/);
    if (img) { figure(img[2], img[1]).forEach((p) => body.push(p)); i++; continue; }

    if (t.startsWith('|')) { const { rows, next } = parseTableAt(i); if (rows.length) body.push(makeTable(rows)); body.push(new Paragraph({ children: [], spacing: { after: 120 } })); i = next; continue; }

    if (/^\d+\.\s/.test(t)) {
        const ref = numberRef(parseInt(t, 10));
        while (i < md.length && /^\d+\.\s/.test(md[i].trim())) { body.push(numbered(md[i].trim().replace(/^\d+\.\s/, ''), ref)); i++; }
        continue;
    }
    if (/^[-*]\s/.test(t)) {
        while (i < md.length && /^[-*]\s/.test(md[i].trim())) { body.push(bullet(md[i].trim().replace(/^[-*]\s/, ''))); i++; }
        continue;
    }

    body.push(para(t)); i++;
}

/* ----------------------------------------------------------- halaman depan */

const centered = (text, o = {}) => new Paragraph({ children: [new TextRun({ text, size: o.size || 22, bold: o.bold, color: o.color || INK })], alignment: AlignmentType.CENTER, spacing: { after: o.after ?? 120 } });

const cover = [
    new Paragraph({ children: [], spacing: { before: 1700 } }),
    centered('PANDUAN PENGGUNAAN DAN PENGUJIAN', { size: 26, bold: true, color: MUTED, after: 360 }),
    new Paragraph({ children: [new TextRun({ text: 'SPK Prioritas Produksi', size: 40, bold: true, color: INK })], alignment: AlignmentType.CENTER, spacing: { after: 200, line: 360 }, border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: INK, space: 12 } } }),
    centered('Studi Kasus: PT Weba International', { size: 24, after: 900 }),
];
if (coverInfo.length) {
    const infoRows = coverInfo.filter((r) => r.length === 2 && r[0].toLowerCase() !== 'informasi');
    cover.push(new Table({
        width: { size: 7200, type: WidthType.DXA }, columnWidths: [2600, 4600], alignment: AlignmentType.CENTER,
        rows: infoRows.map((r) => new TableRow({ children: [cell(r[0], 2600, true), cell(r[1], 4600, false)] })),
    }));
}
cover.push(new Paragraph({ children: [new TextRun({ text: 'Dokumen ini bertambah setiap kali satu fase pembangunan aplikasi selesai.', size: 18, italics: true, color: MUTED })], alignment: AlignmentType.CENTER, spacing: { before: 700 } }));

const tocLine = (item) => new Paragraph({
    tabStops: [{ type: TabStopType.RIGHT, position: CONTENT_WIDTH, leader: 'dot' }],
    indent: { left: item.level === 2 ? 420 : 0 }, spacing: { before: item.level === 1 ? 120 : 20, after: 20 },
    children: [new TextRun({ text: item.text, bold: item.level === 1, size: 22, color: INK }), new TextRun({ children: [new Tab()], size: 22 }), new TextRun({ text: String(PAGES[item.text] ?? ''), bold: item.level === 1, size: 22, color: INK })],
});
const frontTitle = (text) => new Paragraph({ children: [new TextRun({ text, bold: true, size: 30, color: INK })], spacing: { after: 240 } });
const front = [
    frontTitle('Daftar Isi'), ...headings.map(tocLine),
    new Paragraph({ children: [new PageBreak()] }),
    frontTitle('Daftar Gambar'), ...figures.map((text) => tocLine({ level: 2, text })),
];

const margins = { top: 1440, right: 1418, bottom: 1300, left: 1560 };
const pageSize = { width: 11906, height: 16838 };
const footer = new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ children: [PageNumber.CURRENT], size: 18, color: MUTED })] })] });
const header = new Header({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'Panduan Penggunaan dan Pengujian SPK Prioritas Produksi', size: 16, color: MUTED })], border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: RULE, space: 4 } } })] });

const doc = new Document({
    creator: 'Peneliti', title: docTitle,
    styles: {
        default: { document: { run: { font: FONT, size: 24, color: INK } } },
        paragraphStyles: [
            { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 30, bold: true, font: FONT, color: INK }, paragraph: { outlineLevel: 0 } },
            { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 26, bold: true, font: FONT, color: INK }, paragraph: { outlineLevel: 1 } },
        ],
    },
    numbering: { config: numbering },
    sections: [
        { properties: { page: { size: pageSize, margin: margins } }, children: cover },
        { properties: { page: { size: pageSize, margin: margins, pageNumbers: { start: 1, formatType: NumberFormat.LOWER_ROMAN } } }, footers: { default: footer }, children: front },
        { properties: { page: { size: pageSize, margin: margins, pageNumbers: { start: 1, formatType: NumberFormat.DECIMAL } } }, headers: { default: header }, footers: { default: footer }, children: body },
    ],
});

fs.writeFileSync(path.join(DIR, 'targets-doc.json'), JSON.stringify({ headings: headings.map((h) => h.text), captions: figures }, null, 1));
Packer.toBuffer(doc).then((buffer) => { fs.writeFileSync(OUT, buffer); console.log('ditulis', OUT, 'judul', headings.length, 'gambar', figNo); });
