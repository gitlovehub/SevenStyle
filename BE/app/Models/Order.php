<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'total_amount',
        'discount_amount',
        'finnal_amount',
        'payment_method',
        'shipping',
        'o_name',
        'o_address',
        'o_phone',
        'o_mail',
        'stt_track',
        'stt_payment',
    ];
}
