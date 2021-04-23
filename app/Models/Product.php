<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'asin';
    protected $casts = ['asin' => 'string'];

    protected $appends = ['last_price'];

    protected $fillable = [
        'asin',
        'name',
        'category_id'
    ];

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function getLastPriceAttribute()
    {
        return $this->prices()
            ->orderBy('created_at', 'desc')
            ->first()
            ->price ?? 0;
    }
}
