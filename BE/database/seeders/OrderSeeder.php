<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo đơn hàng mẫu
        Order::create([
            'user_id' => 2,
            'code' => 'SHHD',
            'total_amount' => 1000000,
            'discount_amount' => 100000,
            'finnal_amount' => 900000,
            'payment_method' => 'ship_cod',
            'shipping' => 30000,
            'o_name' => 'Hoàng',
            'o_address' => '........',
            'o_phone' => '.......',
            'o_mail' => '......',
            'stt_track' => 1,
            'stt_payment' => 1,
        ]);
    }
}
