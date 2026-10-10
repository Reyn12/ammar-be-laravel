<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /** Profil customer yang sedang login. */
    public function me(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('customer');

        return response()->json(['data' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'avatar_url' => $customer->avatar_url,
        ]]);
    }

    /**
     * Riwayat pesanan: pesanan yang dibuat saat login, ditambah pesanan tamu
     * dengan email yang sama (email Google sudah terverifikasi, jadi aman dicocokkan).
     */
    public function orders(Request $request): AnonymousResourceCollection
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('customer');

        $orders = Order::with(['table', 'payment', 'items.addons'])
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhereRaw('LOWER(customer_email) = ?', [mb_strtolower($customer->email)]);
            })
            ->latest('id')
            ->paginate(20);

        return OrderResource::collection($orders);
    }
}
