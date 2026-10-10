<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;

class BranchController extends Controller
{
    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch->load('openingHours'));
    }
}
