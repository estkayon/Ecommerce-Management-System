<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalProducts = Product::query()->count();

        $totalOrders = Order::query()->count();

        $confirmedPaidOrders = Order::query()
            ->where('status', 'confirmed')
            ->where('payment_status', 'paid');

        $paidOrders = (clone $confirmedPaidOrders)->count();

        $verifiedRevenue = (clone $confirmedPaidOrders)
            ->sum('total');

        $pendingOrders = Order::query()
            ->where('status', 'pending')
            ->count();

        $unresolvedPayments = Payment::query()
            ->whereIn('status', [
                'initiating',
                'pending',
                'processing',
            ])
            ->count();

        $deliveryCounts = Delivery::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => [
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'paid_orders' => $paidOrders,
                'pending_orders' => $pendingOrders,
                'verified_revenue' => number_format(
                    (float) $verifiedRevenue,
                    2,
                    '.',
                    ''
                ),
                'unresolved_payments' => $unresolvedPayments,
                'deliveries' => [
                    'total' => Delivery::query()->count(),
                    'pending' => (int) ($deliveryCounts['pending'] ?? 0),
                    'processing' => (int) ($deliveryCounts['processing'] ?? 0),
                    'created' => (int) ($deliveryCounts['created'] ?? 0),
                    'dispatched' => (int) ($deliveryCounts['dispatched'] ?? 0),
                    'delivered' => (int) ($deliveryCounts['delivered'] ?? 0),
                    'failed' => (int) ($deliveryCounts['failed'] ?? 0),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
