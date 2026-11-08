// Pengaturan bersama skrip tangkap layar. Ubah lewat variabel lingkungan bila perlu:
//   APP_URL        alamat aplikasi, bawaan http://127.0.0.1:8001
//   BROWSER_PATH   lokasi peramban (msedge.exe atau chrome.exe); bila kosong dipakai Microsoft Edge terpasang
const path = require('path');

const BASE = process.env.APP_URL || 'http://127.0.0.1:8001';
const OUT = path.resolve(__dirname, '..', 'gambar-panduan');
const FILES = path.join(__dirname, 'berkas-uji');

function opsiPeramban() {
  return process.env.BROWSER_PATH
    ? { executablePath: process.env.BROWSER_PATH, headless: true }
    : { channel: 'msedge', headless: true };
}

module.exports = { BASE, OUT, FILES, opsiPeramban };
