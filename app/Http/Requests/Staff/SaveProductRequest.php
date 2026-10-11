<?php

namespace App\Http\Requests\Staff;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Dipakai untuk membuat (POST) dan mengubah (PUT) produk. Saat PUT semua field opsional,
 * jadi toggle ketersediaan cukup mengirim {"is_available": false}.
 */
class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'min:2', 'max:150'],
            'price' => [$required, 'integer', 'min:0', 'max:100000000'],
            'is_available' => ['sometimes', 'boolean'],
            // URL penuh atau path relatif hasil upload; path lokal perangkat ditolak (lihat withValidator)
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Isi paket. Kalau dikirim, daftarnya dianggap lengkap; array kosong = bukan paket.
            'components' => ['sometimes', 'array', 'max:20'],
            'components.*.component_id' => ['required', 'integer', 'distinct'],
            'components.*.qty' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'components.*.receives_options' => ['sometimes', 'boolean'],

            'addon_groups' => ['sometimes', 'array', 'max:20'],
            'addon_groups.*.id' => ['nullable', 'integer'],
            'addon_groups.*.name' => ['required', 'string', 'max:100'],
            'addon_groups.*.is_required' => ['sometimes', 'boolean'],
            'addon_groups.*.min_qty' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'addon_groups.*.max_qty' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'addon_groups.*.addons' => ['required', 'array', 'min:1', 'max:50'],
            'addon_groups.*.addons.*.id' => ['nullable', 'integer'],
            'addon_groups.*.addons.*.name' => ['required', 'string', 'max:100'],
            'addon_groups.*.addons.*.price' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'addon_groups.*.addons.*.is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateComponents($validator);

            $image = $this->input('image_url');

            // Foto yang baru dipilih dari galeri harus diunggah dulu lewat /uploads/product-image.
            if (is_string($image) && $image !== '' && ! preg_match('#^(https?://|/?(images|storage)/)#', $image)) {
                $validator->errors()->add('image_url', 'Foto belum diunggah. Unggah dulu lalu kirim URL hasilnya.');
            }

            foreach ((array) $this->input('addon_groups', []) as $index => $group) {
                $min = (int) ($group['min_qty'] ?? (($group['is_required'] ?? false) ? 1 : 0));
                $max = (int) ($group['max_qty'] ?? 1);

                if ($min > $max) {
                    $validator->errors()->add("addon_groups.$index.min_qty", 'Minimal pilihan tidak boleh lebih besar dari maksimal.');
                }

                if ($max > count($group['addons'] ?? [])) {
                    $validator->errors()->add("addon_groups.$index.max_qty", 'Maksimal pilihan melebihi jumlah opsi yang tersedia.');
                }
            }
        });
    }

    /**
     * Isi paket harus menu biasa milik cabang yang sama (bukan paket lain, bukan dirinya sendiri),
     * dan menu yang sudah jadi isi paket lain tidak boleh menjadi paket.
     */
    private function validateComponents(Validator $validator): void
    {
        $components = (array) $this->input('components', []);

        if ($components === []) {
            return;
        }

        $product = $this->route('product');
        $branchId = $this->user()->branch_id;

        if ($product instanceof Product && $product->usedInPackages()->whereHas('product')->exists()) {
            $validator->errors()->add('components', "{$product->name} sudah menjadi isi paket lain, jadi tidak bisa dijadikan paket.");
        }

        $found = Product::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', collect($components)->pluck('component_id')->filter()->all())
            ->withCount('components')
            ->get()
            ->keyBy('id');

        foreach ($components as $index => $component) {
            $id = $component['component_id'] ?? null;
            $menu = $found->get($id);

            $error = match (true) {
                $id === null => null,
                ! $menu => 'Menu isi paket tidak ditemukan.',
                $product instanceof Product && $product->id === $menu->id => 'Paket tidak bisa berisi dirinya sendiri.',
                $menu->components_count > 0 => "{$menu->name} adalah paket, tidak bisa jadi isi paket lain.",
                default => null,
            };

            if ($error) {
                $validator->errors()->add("components.$index.component_id", $error);
            }
        }
    }
}
