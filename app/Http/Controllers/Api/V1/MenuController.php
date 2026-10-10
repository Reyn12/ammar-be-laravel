<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Menu\GetBranchMenu;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    public function index(Request $request, Branch $branch, GetBranchMenu $action): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return MenuCategoryResource::collection(
            $action->handle($branch, $validated['q'] ?? null)
        );
    }
}
