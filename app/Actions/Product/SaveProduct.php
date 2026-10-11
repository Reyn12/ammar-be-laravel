<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\ProductComponent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    /**
     * Buat atau ubah produk beserta variant/addon-nya dalam satu transaksi.
     *
     * Kalau "addon_groups" dikirim, daftarnya dianggap lengkap: grup/opsi yang tidak ikut
     * dikirim diarsipkan (bukan dihapus) supaya riwayat pesanan tetap utuh.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(User $staff, array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($staff, $data, $product) {
            $attributes = collect($data)->only(['category_id', 'name', 'price', 'is_available', 'image_url'])->all();

            if ($product) {
                $product->update($attributes);
            } else {
                $product = Product::create($attributes + [
                    'branch_id' => $staff->branch_id,
                    'is_available' => true,
                ]);
            }

            if (array_key_exists('addon_groups', $data)) {
                $this->syncGroups($product, $data['addon_groups']);
            }

            if (array_key_exists('components', $data)) {
                $this->syncComponents($product, $data['components']);
            }

            return $product->load(['category', 'addonGroups.addons', 'components.component']);
        });
    }

    /**
     * Isi paket diganti sesuai daftar yang dikirim. Pesanan lama tidak terpengaruh karena
     * isi paketnya sudah disalin ke item pesanan saat dibuat.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    private function syncComponents(Product $product, array $components): void
    {
        $keptIds = [];

        foreach ($components as $component) {
            ProductComponent::updateOrCreate(
                ['product_id' => $product->id, 'component_id' => $component['component_id']],
                ['qty' => (int) ($component['qty'] ?? 1), 'receives_options' => (bool) ($component['receives_options'] ?? false)],
            );

            $keptIds[] = $component['component_id'];
        }

        $product->components()->whereNotIn('component_id', $keptIds)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function syncGroups(Product $product, array $groups): void
    {
        $existing = $product->addonGroups()->get()->keyBy('id');
        $keptGroupIds = [];

        foreach ($groups as $groupData) {
            $isRequired = (bool) ($groupData['is_required'] ?? false);

            $attributes = [
                'name' => $groupData['name'],
                'is_required' => $isRequired,
                'min_qty' => (int) ($groupData['min_qty'] ?? ($isRequired ? 1 : 0)),
                'max_qty' => (int) ($groupData['max_qty'] ?? 1),
            ];

            $group = $this->findOwned($existing, $groupData['id'] ?? null, 'grup pilihan');

            if ($group) {
                $group->update($attributes);
            } else {
                $group = $product->addonGroups()->create($attributes);
            }

            $keptGroupIds[] = $group->id;
            $this->syncAddons($group, $groupData['addons']);
        }

        $product->addonGroups()->whereNotIn('id', $keptGroupIds)->get()->each(function (ProductAddonGroup $group) {
            $group->addons()->delete();
            $group->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $addons
     */
    private function syncAddons(ProductAddonGroup $group, array $addons): void
    {
        $existing = $group->addons()->get()->keyBy('id');
        $keptIds = [];

        foreach ($addons as $addonData) {
            $attributes = [
                'name' => $addonData['name'],
                'price' => (int) ($addonData['price'] ?? 0),
                'is_available' => (bool) ($addonData['is_available'] ?? true),
            ];

            $addon = $this->findOwned($existing, $addonData['id'] ?? null, 'opsi');

            if ($addon) {
                $addon->update($attributes);
            } else {
                $addon = $group->addons()->create($attributes);
            }

            $keptIds[] = $addon->id;
        }

        $group->addons()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Id yang dikirim harus milik produk/grup ini, supaya produk lain tidak bisa diutak-atik.
     *
     * @param  Collection<int, ProductAddonGroup|ProductAddon>  $existing
     */
    private function findOwned($existing, mixed $id, string $label): ProductAddonGroup|ProductAddon|null
    {
        if ($id === null) {
            return null;
        }

        $found = $existing->get($id);

        if (! $found) {
            throw ValidationException::withMessages(['addon_groups' => "Data $label dengan id $id bukan milik produk ini."]);
        }

        return $found;
    }
}
