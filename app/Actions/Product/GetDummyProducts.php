<?php

namespace App\Actions\Product;

use Illuminate\Support\Collection;

class GetDummyProducts
{
    /**
     * @return Collection<int, array{id: int, name: string, price: int, category: string, is_available: bool}>
     */
    public function handle(): Collection
    {
        return collect([
            [
                'id' => 1,
                'name' => 'Nasi Goreng Spesial',
                'price' => 25000,
                'category' => 'Makanan',
                'is_available' => true,
            ],
            [
                'id' => 2,
                'name' => 'Mie Ayam Bakso',
                'price' => 20000,
                'category' => 'Makanan',
                'is_available' => true,
            ],
            [
                'id' => 3,
                'name' => 'Es Teh Manis',
                'price' => 5000,
                'category' => 'Minuman',
                'is_available' => true,
            ],
            [
                'id' => 4,
                'name' => 'Jus Alpukat',
                'price' => 18000,
                'category' => 'Minuman',
                'is_available' => false,
            ],
            [
                'id' => 5,
                'name' => 'Pisang Goreng Coklat',
                'price' => 12000,
                'category' => 'Snack',
                'is_available' => true,
            ],
        ]);
    }
}
