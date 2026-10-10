<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /** Satu gambar dulu untuk semua produk (file ada di public/images). */
    private const PLACEHOLDER_IMAGE = 'images/menu-placeholder.png';

    private const PLAIN_DRINKS = ['air mineral', 'teh botol', 'es batu kristal'];

    public function run(): void
    {
        $branch = Branch::where('name', BranchSeeder::BRANCH_NAME)->firstOrFail();

        /** @var array<string, array<string, int>> $menu */
        $menu = require database_path('seeders/data/menu.php');

        foreach ($menu as $categoryName => $products) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($products as $name => $price) {
                $product = Product::firstOrCreate(
                    ['branch_id' => $branch->id, 'name' => $name],
                    [
                        'category_id' => $category->id,
                        'price' => $price,
                        'image_url' => self::PLACEHOLDER_IMAGE,
                        'is_available' => true,
                    ],
                );

                // Grup variant/addon hanya dibuat saat produk baru, supaya seeder aman diulang.
                if ($product->wasRecentlyCreated) {
                    $this->seedOptions($product, $categoryName);
                }
            }
        }
    }

    private function seedOptions(Product $product, string $categoryName): void
    {
        if ($product->name === 'Ayam Bakar / Goreng 1 Potong') {
            $this->variant($product, 'Tipe Ayam', ['Bakar' => 0, 'Goreng' => 0]);
            $this->variant($product, 'Level Pedas', ['Lv 0' => 0, 'Lv 1' => 2000, 'Lv 2' => 3000]);
            $this->addon($product, 'Tambahan', [
                'Extra Sambal' => 3000,
                'Extra Lalapan' => 5000,
                'Extra Nasi' => 5000,
            ]);

            return;
        }

        if ($categoryName === 'Menu Minuman') {
            $this->seedDrinkOptions($product);
        }
    }

    private function seedDrinkOptions(Product $product): void
    {
        $name = mb_strtolower($product->name);

        if (in_array($name, self::PLAIN_DRINKS, true)) {
            return;
        }

        $isHot = (bool) preg_match('/hangat|panas/', $name);
        $isCoffee = (bool) preg_match('/kopi|coffee|indocafe/', $name);

        if ($isHot) {
            $this->variant($product, 'Level Gula', $this->sugarLevels());
        } elseif ($isCoffee && ! str_starts_with($name, 'es ')) {
            $this->variant($product, 'Penyajian', ['Es' => 0, 'Hangat' => 0]);
            $this->variant($product, 'Level Gula', $this->sugarLevels());
        } else {
            $this->variant($product, 'Level Es', ['Normal' => 0, 'Sedikit Es' => 0, 'Tanpa Es' => 0]);
            $this->variant($product, 'Level Gula', $this->sugarLevels());
        }

        if ($isCoffee) {
            $this->addon($product, 'Tambahan', ['Extra Susu' => 3000]);
        }
    }

    /** @return array<string, int> */
    private function sugarLevels(): array
    {
        return ['Normal' => 0, 'Less Sugar' => 0, 'Tanpa Gula' => 0];
    }

    /** Variant: wajib pilih satu. */
    private function variant(Product $product, string $group, array $choices): void
    {
        $this->group($product, $group, true, 1, 1, $choices);
    }

    /** Addon: opsional, boleh pilih beberapa. */
    private function addon(Product $product, string $group, array $choices): void
    {
        $this->group($product, $group, false, 0, count($choices), $choices);
    }

    private function group(Product $product, string $name, bool $required, int $min, int $max, array $choices): void
    {
        $group = $product->addonGroups()->create([
            'name' => $name,
            'is_required' => $required,
            'min_qty' => $min,
            'max_qty' => $max,
        ]);

        foreach ($choices as $label => $price) {
            $group->addons()->create(['name' => $label, 'price' => $price]);
        }
    }
}
