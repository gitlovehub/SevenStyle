<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'name',
        'height',
        'width',
        'length',
        'weight',
    ];
    public function products()
    {
        return $this->hasMany(Product::class, 'box_id', 'id');
    }
}
