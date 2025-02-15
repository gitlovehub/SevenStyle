<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_percent',
        'amount',
        'max_discount_amount',
        'min_product_price',
        'usage_limit',
        'expiry_date',
        'start_date',
        'times_used',
    ];

    // Đảm bảo rằng giá trị times_used được khởi tạo là 0 khi tạo voucher mới
    protected $attributes = [
        'times_used' => 0,
    ];
    public function usages()
    {
        return $this->hasMany(VoucherUsage::class);
    }
}
