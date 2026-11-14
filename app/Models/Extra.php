<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;

class Extra extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'product_type' => ProductType::class,
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
