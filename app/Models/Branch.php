<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address'])]
class Branch extends Model
{
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
