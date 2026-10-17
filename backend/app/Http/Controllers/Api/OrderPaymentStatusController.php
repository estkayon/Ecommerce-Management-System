<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPaymentStatusController extends Controller
{
    public function show(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payment_access_token' => [
                'required',
                'string',
                'size:64',
            ],
        ]);

        $providedHash = hash(
            'sha256',
            $validated['payment_access_token']
        );

        if (
            ! $order->payment_access_token_hash ||
            ! hash_equals(
                $order->payment_access_token_hash,
                $providedHash
            )
        ) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        $order->load('delivery');

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'delivery_status' => $order->delivery_status,
                'total' => $order->total,
                'delivery' => $order->delivery ? [
                    'status' => $order->delivery->status,
                    'tracking_number' =>
                        $order->delivery->tracking_number,
                ] : null,
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
