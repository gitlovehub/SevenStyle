<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Voucher;
use Illuminate\Support\Str;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo 10 voucher mẫu
        for ($i = 1; $i <= 10; $i++) {
            Voucher::create([
                'code' => 'VOUCHER' . $i,
                'name' => 'Giảm giá ' . $i,
                'description' => 'Mô tả cho voucher ' . $i,
                'discount_percent' => ($i % 2 == 0) ? rand(10, 50) : null,
                'amount' => ($i % 2 != 0) ? rand(10000, 50000) : null,
                'type' => $i % 2 == 0 ? 1 : 0,
                'for_logged_in_users' => false,
                'max_discount_amount' => ($i % 2 == 0) ? rand(10000, 50000) : null,
                'min_product_price' => ($i % 2 == 0) ? rand(100000, 500000) : null,
                'usage_limit' => rand(1, 10),
                'expiry_date' => now()->addDays(rand(1, 30)),
                'start_date' => now()->subDays(rand(1, 30)),
                'times_used' => 0,
            ]);
        }
    }
}
