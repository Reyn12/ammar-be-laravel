<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['branch_id', 'category_id', 'image_url', 'name', 'price', 'is_available'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        // withTrashed: produk lama tetap tahu nama kategorinya walau kategorinya sudah diarsipkan.
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addonGroups(): HasMany
    {
        return $this->hasMany(ProductAddonGroup::class);
    }

    /** Paket-paket yang memakai produk ini sebagai isi. */
    public function usedInPackages(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'component_id');
    }

    /** Isi paket (kosong untuk produk biasa). */
    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class)->orderBy('id');
    }
}
