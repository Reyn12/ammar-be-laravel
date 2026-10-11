<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Product\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SaveProductRequest;
use App\Http\Resources\StaffProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffProductController extends Controller
{
    private const WITH = ['category', 'addonGroups.addons', 'components.component'];

    /** Seluruh produk cabang, termasuk yang sedang habis (yang diarsipkan tidak ikut). */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(self::WITH)
            ->where('branch_id', $request->user()->branch_id)
            ->orderBy('category_id')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(StaffProductResource::collection($products)->resolve());
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success(
            Category::withCount('products')->orderBy('id')->get()->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'product_count' => $category->products_count,
            ])->values(),
        );
    }

    public function store(SaveProductRequest $request, SaveProduct $action): JsonResponse
    {
        $product = $action->handle($request->user(), $request->validated());

        return ApiResponse::success((new StaffProductResource($product))->resolve(), 'Menu ditambahkan.', 201);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $action): JsonResponse
    {
        $this->ensureSameBranch($request, $product);

        $updated = $action->handle($request->user(), $request->validated(), $product);

        return ApiResponse::success((new StaffProductResource($updated))->resolve(), 'Menu diperbarui.');
    }

    /** Diarsipkan, bukan dihapus: riwayat pesanan tetap utuh, menu hilang dari daftar customer. */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->ensureSameBranch($request, $product);

        $product->delete();

        return ApiResponse::success(null, 'Menu dihapus.');
    }

    private function ensureSameBranch(Request $request, Product $product): void
    {
        abort_if($product->branch_id !== $request->user()->branch_id, 404);
    }
}
