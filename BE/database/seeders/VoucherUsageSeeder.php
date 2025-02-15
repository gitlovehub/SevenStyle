<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VoucherUsage;

class VoucherUsageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo 10 lần sử dụng voucher mẫu
        for ($i = 1; $i <= 10; $i++) {
            VoucherUsage::create([
                'user_id' => rand(1, 10),
                'voucher_id' => rand(1, 10),
                'order_id' => rand(1, 10),
            ]);
        }
    }
}
