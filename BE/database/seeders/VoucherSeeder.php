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
        $vouchers = [
            [
                'code' => 'VOUCHER1',
                'name' => 'Giảm giá 10%',
                'description' => 'Voucher giảm 10% cho đơn hàng đầu tiên',
                'discount_percent' => 10,
                'amount' => null,
                'type' => 1,
                'for_logged_in_users' => true,
                'max_discount_amount' => 20000,
                'min_product_price' => 100000,
                'usage_limit' => 5,
                'expiry_date' => now()->addDays(10),
                'start_date' => now()->subDays(5),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER2',
                'name' => 'Giảm giá 20%',
                'description' => 'Voucher giảm 20% cho đơn hàng trên 200k',
                'discount_percent' => 20,
                'amount' => null,
                'type' => 1,
                'for_logged_in_users' => false,
                'max_discount_amount' => 50000,
                'min_product_price' => 200000,
                'usage_limit' => 10,
                'expiry_date' => now()->addDays(15),
                'start_date' => now()->subDays(3),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER3',
                'name' => 'Giảm ngay 50K',
                'description' => 'Giảm ngay 50,000đ cho đơn hàng bất kỳ',
                'discount_percent' => null,
                'amount' => 50000,
                'type' => 0,
                'for_logged_in_users' => true,
                'max_discount_amount' => null,
                'min_product_price' => null,
                'usage_limit' => 3,
                'expiry_date' => now()->addDays(20),
                'start_date' => now()->subDays(2),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER4',
                'name' => 'Giảm giá 30%',
                'description' => 'Voucher giảm 30% cho đơn hàng trên 300k',
                'discount_percent' => 30,
                'amount' => null,
                'type' => 1,
                'for_logged_in_users' => false,
                'max_discount_amount' => 70000,
                'min_product_price' => 300000,
                'usage_limit' => 8,
                'expiry_date' => now()->addDays(25),
                'start_date' => now()->subDays(10),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER5',
                'name' => 'Giảm ngay 100K',
                'description' => 'Giảm ngay 100,000đ cho đơn hàng từ 500k',
                'discount_percent' => null,
                'amount' => 100000,
                'type' => 0,
                'for_logged_in_users' => true,
                'max_discount_amount' => null,
                'min_product_price' => 500000,
                'usage_limit' => 6,
                'expiry_date' => now()->addDays(30),
                'start_date' => now()->subDays(15),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER6',
                'name' => 'Giảm giá 40%',
                'description' => 'Voucher giảm 40% cho đơn hàng trên 400k',
                'discount_percent' => 40,
                'amount' => null,
                'type' => 1,
                'for_logged_in_users' => false,
                'max_discount_amount' => 80000,
                'min_product_price' => 400000,
                'usage_limit' => 4,
                'expiry_date' => now()->addDays(12),
                'start_date' => now()->subDays(7),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER7',
                'name' => 'Giảm ngay 20K',
                'description' => 'Giảm ngay 20,000đ cho mọi đơn hàng',
                'discount_percent' => null,
                'amount' => 20000,
                'type' => 0,
                'for_logged_in_users' => true,
                'max_discount_amount' => null,
                'min_product_price' => null,
                'usage_limit' => 15,
                'expiry_date' => now()->addDays(5),
                'start_date' => now()->subDays(1),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER8',
                'name' => 'Giảm giá 50%',
                'description' => 'Voucher giảm 50% cho đơn hàng trên 500k',
                'discount_percent' => 50,
                'amount' => null,
                'type' => 1,
                'for_logged_in_users' => false,
                'max_discount_amount' => 100000,
                'min_product_price' => 500000,
                'usage_limit' => 2,
                'expiry_date' => now()->addDays(18),
                'start_date' => now()->subDays(8),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER9',
                'name' => 'Giảm ngay 70K',
                'description' => 'Giảm ngay 70,000đ cho đơn hàng từ 700k',
                'discount_percent' => null,
                'amount' => 70000,
                'type' => 0,
                'for_logged_in_users' => true,
                'max_discount_amount' => null,
                'min_product_price' => 700000,
                'usage_limit' => 5,
                'expiry_date' => now()->addDays(22),
                'start_date' => now()->subDays(6),
                'times_used' => 0,
            ],
            [
                'code' => 'VOUCHER10',
                'name' => 'Giảm giá 25%',
                'description' => 'Voucher giảm 25% cho đơn hàng trên 250k',
                'discount_percent' => 25,
                'amount' => null,
                'type' => 1,
                'for_logged_in_users' => false,
                'max_discount_amount' => 60000,
                'min_product_price' => 250000,
                'usage_limit' => 7,
                'expiry_date' => now()->addDays(28),
                'start_date' => now()->subDays(12),
                'times_used' => 0,
            ]
        ];

        // Tạo voucher trong database
        foreach ($vouchers as $voucher) {
            Voucher::create($voucher);
        }

    }
}
