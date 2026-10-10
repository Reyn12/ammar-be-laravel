<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
}
