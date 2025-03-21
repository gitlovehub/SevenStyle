<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariationValue extends Model
{
    use HasFactory,SoftDeletes ;
    
    protected $fillable = [
        'variation_id',
        'attribute_value_id'
    ];
    public function attributeValue() 
    {
        return $this->belongsTo(AttributeValue::class,'attribute_value_id','id'); 
    }
    public function productAttributes(){
        return $this->hasMany(ProductAttribute ::class );
    }
}
