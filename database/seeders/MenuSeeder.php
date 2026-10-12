<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /** Dipakai kalau foto produk belum ada di public/images/menus. */
    private const PLACEHOLDER_IMAGE = 'images/menu-placeholder.png';

    private const MENU_IMAGE_DIR = 'images/menus';

    private const PLAIN_DRINKS = ['air mineral', 'teh botol', 'es batu kristal'];

    private const PACKAGE_CATEGORY = 'Menu Paket';

    /** Nama paket lama => nama baru (nama tidak lagi memuat harga). Id produk tetap. */
    private const RENAMED = [
        'Paket 24K (Tanpa Minuman)' => 'Paket Hemat 1',
        'Paket 26K (Air Mineral)' => 'Paket Hemat 2',
        'Paket 27K (Es Teh Manis)' => 'Paket Hemat 3',
        'Paket 28K (Nasi Liwet)' => 'Paket Hemat 4',
    ];

    /**
     * Isi paket: [nama komponen, jumlah, ikut menerima pilihan paket (bakar/goreng, catatan)].
     * Saat dipesan, paket dipecah jadi item dapur per komponen.
     */
    private const BUNDLES = [
        'Paket Hemat 1' => [
            ['Ayam Bakar / Goreng 1 Potong', 1, true],
            ['Nasi Putih Porsi', 1, false],
            ['Lalapan', 1, false],
            ['Sambal Dadakan', 1, false],
        ],
        'Paket Hemat 2' => [
            ['Ayam Bakar / Goreng 1 Potong', 1, true],
            ['Nasi Putih Porsi', 1, false],
            ['Lalapan', 1, false],
            ['Sambal Dadakan', 1, false],
            ['Air Mineral', 1, false],
        ],
        'Paket Hemat 3' => [
            ['Ayam Bakar / Goreng 1 Potong', 1, true],
            ['Nasi Putih Porsi', 1, false],
            ['Lalapan', 1, false],
            ['Sambal Dadakan', 1, false],
            ['Es Teh Manis', 1, false],
        ],
        'Paket Hemat 4' => [
            ['Ayam Bakar / Goreng 1 Potong', 1, true],
            ['Nasi Liwet Porsi', 1, false],
            ['Lalapan', 1, false],
            ['Sambal Dadakan', 1, false],
        ],
    ];

    public function run(): void
    {
        $branch = Branch::where('name', BranchSeeder::BRANCH_NAME)->firstOrFail();

        /** @var array<string, array<string, int>> $menu */
        $menu = require database_path('seeders/data/menu.php');

        foreach (self::RENAMED as $oldName => $newName) {
            Product::where('branch_id', $branch->id)->where('name', $oldName)->update(['name' => $newName]);
        }

        foreach ($menu as $categoryName => $products) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($products as $name => $price) {
                // Kategori, harga, dan foto bawaan mengikuti data seeder; ketersediaan tidak ditimpa.
                $product = Product::firstOrNew(['branch_id' => $branch->id, 'name' => $name], [
                    'is_available' => true,
                ]);
                $product->fill(['category_id' => $category->id, 'price' => $price]);

                if ($this->canReplaceImage($product)) {
                    $product->image_url = $this->imageFor($name);
                }

                $product->save();

                // Grup variant/addon hanya dibuat saat produk baru, supaya seeder aman diulang.
                if ($product->wasRecentlyCreated) {
                    $this->seedOptions($product, $categoryName);
                }

                if ($categoryName === self::PACKAGE_CATEGORY) {
                    $this->seedPackage($product, $branch);
                }
            }
        }
    }

    /** Foto hasil unggahan staff tidak ditimpa; hanya kosong, placeholder, atau foto bawaan. */
    private function canReplaceImage(Product $product): bool
    {
        $current = $product->image_url;

        return $current === null
            || $current === self::PLACEHOLDER_IMAGE
            || str_starts_with($current, self::MENU_IMAGE_DIR.'/');
    }

    /** Foto menu: public/images/menus/{slug-nama}.jpeg, fallback ke placeholder. */
    private function imageFor(string $name): string
    {
        $path = self::MENU_IMAGE_DIR.'/'.Str::slug($name).'.jpeg';

        return file_exists(public_path($path)) ? $path : self::PLACEHOLDER_IMAGE;
    }

    /** Pilihan ayam untuk paket dan isi paketnya. Aman dijalankan ulang. */
    private function seedPackage(Product $package, Branch $branch): void
    {
        if (! $package->addonGroups()->where('name', 'Tipe Ayam')->exists()) {
            $this->variant($package, 'Tipe Ayam', ['Bakar' => 0, 'Goreng' => 0]);
            $this->variant($package, 'Level Pedas', ['Lv 0' => 0, 'Lv 1' => 2000, 'Lv 2' => 3000]);
        }

        foreach (self::BUNDLES[$package->name] ?? [] as [$componentName, $qty, $receivesOptions]) {
            $component = Product::where('branch_id', $branch->id)->where('name', $componentName)->firstOrFail();

            ProductComponent::updateOrCreate(
                ['product_id' => $package->id, 'component_id' => $component->id],
                ['qty' => $qty, 'receives_options' => $receivesOptions],
            );
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
