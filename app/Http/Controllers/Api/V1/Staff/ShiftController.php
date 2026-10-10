<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Shift\CloseShift;
use App\Actions\Shift\OpenShift;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /** Shift aktif milik kasir yang login; data = null kalau belum buka shift. */
    public function active(Request $request): JsonResponse
    {
        $shift = Shift::withTotals()->with('user')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        return ApiResponse::success($shift ? (new ShiftResource($shift))->resolve() : null);
    }

    public function open(Request $request, OpenShift $action): JsonResponse
    {
        $data = $request->validate(['starting_cash' => ['required', 'integer', 'min:0']]);

        $shift = $action->handle($request->user(), $data['starting_cash']);

        return ApiResponse::success($this->payload($shift), 'Shift dibuka.', 201);
    }

    public function close(Request $request, Shift $shift, CloseShift $action): JsonResponse
    {
        abort_if($shift->user_id !== $request->user()->id, 404);

        $data = $request->validate(['actual_cash' => ['required', 'integer', 'min:0']]);

        return ApiResponse::success($this->payload($action->handle($shift, $data['actual_cash'])), 'Shift ditutup.');
    }

    /** Owner: seluruh shift di cabangnya (terbaru dulu). */
    public function index(Request $request): JsonResponse
    {
        $shifts = Shift::withTotals()->with('user')
            ->whereHas('user', fn ($user) => $user->where('branch_id', $request->user()->branch_id))
            ->latest('id')
            ->limit(100)
            ->get();

        return ApiResponse::success(ShiftResource::collection($shifts)->resolve());
    }

    /** Owner menutup paksa shift kasir yang masih aktif. */
    public function forceClose(Request $request, Shift $shift, CloseShift $action): JsonResponse
    {
        abort_if($shift->user->branch_id !== $request->user()->branch_id, 404);

        $data = $request->validate(['actual_cash' => ['required', 'integer', 'min:0']]);

        return ApiResponse::success($this->payload($action->handle($shift, $data['actual_cash'])), 'Shift ditutup paksa.');
    }

    /** @return array<string, mixed> */
    private function payload(Shift $shift): array
    {
        $fresh = Shift::withTotals()->with('user')->findOrFail($shift->id);

        return (new ShiftResource($fresh))->resolve();
    }
}
