<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner mengelola kategori menu. Menghapus = mengarsipkan, dan hanya boleh kalau kategorinya
 * sudah tidak punya menu aktif.
 */
class StaffCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $category = Category::create($this->validated($request));

        return ApiResponse::success($this->payload($category), 'Kategori ditambahkan.', 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $category->update($this->validated($request, $category));

        return ApiResponse::success($this->payload($category), 'Kategori diperbarui.');
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Kategori ini masih punya menu. Pindahkan atau hapus menunya dulu.',
            ]);
        }

        $category->delete();

        return ApiResponse::success(null, 'Kategori dihapus.');
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:50',
                Rule::unique('categories', 'name')->whereNull('deleted_at')->ignore($category?->id),
            ],
        ], ['name.unique' => 'Nama kategori sudah dipakai.']);
    }

    /** @return array{id: int, name: string, product_count: int} */
    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'product_count' => $category->products()->count(),
        ];
    }
}
