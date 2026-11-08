<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoMasterSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['PLG-001', 'PT Langkah Mandiri', '021-5551001', 5],
            ['PLG-002', 'CV Sinar Kulit Nusantara', '0812-3456-7001', 4],
            ['PLG-003', 'Toko Sepatu Berkah Jaya', '0813-2222-8002', 3],
            ['PLG-004', 'PT Garuda Safety Indonesia', '021-5551004', 4],
            ['PLG-005', 'Koperasi Seragam Sekolah Mekar', '0857-1111-9005', 2],
        ];

        foreach ($customers as [$code, $name, $contact, $urgency]) {
            Customer::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'contact' => $contact, 'urgency_level' => $urgency, 'is_active' => true],
            );
        }

        $products = [
            ['PRD-001', 'Sepatu Pantofel Pria', 'Sepatu', 185000, 55],
            ['PRD-002', 'Sepatu Safety Steel Toe', 'Safety', 320000, 80],
            ['PRD-003', 'Sandal Kulit Wanita', 'Sandal', 120000, 35],
            ['PRD-004', 'Sepatu Sekolah Hitam', 'Sepatu', 95000, 40],
        ];

        foreach ($products as [$code, $name, $category, $price, $minutes]) {
            Product::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'unit_price' => $price, 'minutes_per_unit' => $minutes, 'is_active' => true],
            );
        }
    }
}
