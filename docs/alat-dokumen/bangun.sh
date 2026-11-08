#!/usr/bin/env bash
# Membangun docx dari berkas markdown di akar repo.
#   bash bangun.sh panduan    Panduan-Penggunaan-dan-Pengujian-SPK.md  ->  .docx
#   bash bangun.sh prd        PRD-SPK-Prioritas-Produksi-SAW.md        ->  .docx
# Dibangun dua kali karena nomor halaman daftar isi dibaca dari PDF hasil putaran pertama.
set -euo pipefail

cd "$(dirname "$0")"

case "${1:-}" in
  panduan) SKRIP=build-panduan.js; AWAL=doc;  HASIL=Panduan-Penggunaan-dan-Pengujian-SPK.docx ;;
  prd)     SKRIP=build-prd.js;     AWAL=prd;  HASIL=PRD-SPK-Prioritas-Produksi-SAW.docx ;;
  *) echo "Pakai: bash bangun.sh panduan|prd"; exit 1 ;;
esac

[ -d node_modules/docx ] || { echo "Jalankan dulu: npm install (di folder ini)"; exit 1; }
python -c "import fitz" 2>/dev/null || { echo "Pasang dulu: pip install pymupdf"; exit 1; }

if [ -n "${SOFFICE:-}" ]; then
  SO="$SOFFICE"
elif command -v soffice >/dev/null 2>&1; then
  SO="soffice"
elif [ -x "/c/Program Files/LibreOffice/program/soffice.exe" ]; then
  SO="/c/Program Files/LibreOffice/program/soffice.exe"
else
  echo "LibreOffice tidak ditemukan. Pasang LibreOffice atau set SOFFICE ke lokasi soffice."; exit 1
fi

DOCX="$AWAL.docx"
PDF="$AWAL.pdf"
HALAMAN="pages-$AWAL.json"
TARGET="targets-$AWAL.json"
PUTARAN1="p1-$AWAL.json"

ubah_ke_pdf() {
  rm -f "$PDF"
  "$SO" --headless --convert-to pdf "$DOCX" >/dev/null 2>&1
  [ -f "$PDF" ] || { echo "Konversi PDF gagal"; exit 1; }
}

rm -f "$HALAMAN"
for i in 1 2; do
  node "$SKRIP" >/dev/null
  ubah_ke_pdf
  python pages.py "$PDF" "$TARGET" "$HALAMAN"
done
cp "$HALAMAN" "$PUTARAN1"
node "$SKRIP" >/dev/null
ubah_ke_pdf
python pages.py "$PDF" "$TARGET" "$HALAMAN"
diff "$PUTARAN1" "$HALAMAN" >/dev/null && echo "Nomor halaman stabil" || echo "PERINGATAN: nomor halaman daftar isi belum stabil, jalankan ulang"

python -c "import fitz;print('Jumlah halaman:', fitz.open('$PDF').page_count)"

cp "$DOCX" "../../$HASIL"
echo "Selesai: $HASIL di akar repo"

# Pemeriksaan skema Word bersifat opsional; hanya jalan bila skill docx Claude terpasang.
VALIDATOR=$(ls -d "$HOME"/.claude/skills/synced/*/docx/scripts/office/validate.py 2>/dev/null | head -1 || true)
if [ -n "$VALIDATOR" ]; then python "$VALIDATOR" "$DOCX" | tail -1; fi
