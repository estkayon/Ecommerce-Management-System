<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'in:initiating,pending,processing,paid,failed',
            ],
        ]);

        $payments = Payment::query()
            ->with([
                'order:id,order_number,customer_name,total,status,payment_status',
            ])
            ->when(
                $validated['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('transaction_id', 'ilike', "%{$search}%")
                            ->orWhereHas(
                                'order',
                                function ($orderQuery) use ($search) {
                                    $orderQuery
                                        ->where('order_number', 'ilike', "%{$search}%")
                                        ->orWhere('customer_name', 'ilike', "%{$search}%");
                                }
                            );
                    });
                }
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) =>
                    $query->where('status', $status)
            )
            ->latest('id')
            ->paginate(15);

        return response()->json($payments)
            ->header('Cache-Control', 'no-store');
    }
}
