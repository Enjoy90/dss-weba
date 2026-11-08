<?php

namespace App\Console\Commands;

use App\Services\Export\OrderSheetWriter;
use Illuminate\Console\Command;

class MakeSampleImportFile extends Command
{
    protected $signature = 'orders:sample-import {path=contoh-20-order.xlsx : Lokasi berkas yang dibuat}';

    protected $description = 'Membuat berkas Excel contoh untuk mencoba impor order: 20 baris benar dan 2 baris rusak, memakai kode pelanggan dan produk dari data demo';

    public function handle(OrderSheetWriter $writer): int
    {
        $path = (string) $this->argument('path');

        $writer->writeSample($path);

        $this->info("Berkas contoh dibuat: {$path}");
        $this->line('Isinya 20 order yang benar, ditambah 2 baris rusak (baris 22 dan 23) untuk melihat pesan kesalahan.');

        return self::SUCCESS;
    }
}
