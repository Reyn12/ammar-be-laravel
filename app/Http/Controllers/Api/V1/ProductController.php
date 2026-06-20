<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Product\GetDummyProducts;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function dummy(GetDummyProducts $action): JsonResponse
    {
        $products = $action->handle();

        return response()->json([
            'data' => $products->values(),
            'message' => 'Dummy products retrieved successfully.',
        ]);
    }
}
