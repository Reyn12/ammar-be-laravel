<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\ApplyTripayStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Tripay\TripayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripayCallbackController extends Controller
{
    public function __invoke(Request $request, TripayClient $tripay, ApplyTripayStatus $applyStatus): JsonResponse
    {
        if (! $tripay->isValidCallbackSignature($request->getContent(), $request->header('X-Callback-Signature'))) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        if ($request->header('X-Callback-Event') !== 'payment_status') {
            return response()->json(['success' => false, 'message' => 'Unrecognized callback event'], 400);
        }

        $payment = Payment::where('merchant_ref', $request->input('merchant_ref'))->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $applyStatus->handle($payment, $request->all());

        return response()->json(['success' => true]);
    }
}
