<?php

namespace App\Actions\Menu;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class GetBranchMenu
{
    /**
     * Menu satu cabang, dikelompokkan per kategori beserta addon group-nya.
     * Kategori yang tidak punya produk (setelah filter) tidak ikut dikembalikan.
     *
     * @return Collection<int, Category>
     */
    public function handle(Branch $branch, ?string $search = null): Collection
    {
        $search = trim((string) $search);

        return Category::query()
            ->whereHas('products', fn ($products) => $this->scope($products, $branch, $search))
            ->with(['products' => function ($products) use ($branch, $search) {
                $this->scope($products, $branch, $search)
                    ->with(['addonGroups.addons', 'components.component'])
                    ->orderBy('id');
            }])
            ->orderBy('id')
            ->get();
    }

    private function scope($products, Branch $branch, string $search)
    {
        return $products
            ->where('branch_id', $branch->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"));
    }
}
