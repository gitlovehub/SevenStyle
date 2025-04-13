<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpamLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'action',
        'ip',
        'user_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
