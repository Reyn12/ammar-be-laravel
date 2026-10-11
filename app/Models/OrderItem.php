<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'parent_item_id',
    'product_id',
    'batch_id',
    'product_name',
    'qty',
    'price',
    'status',
    'notes',
])]
class OrderItem extends Model
{
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Item paket yang memuat komponen ini (null untuk item biasa). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    /** Komponen paket yang dipecah dari item ini. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }
}
