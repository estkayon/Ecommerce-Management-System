<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentSettingController extends Controller
{
    private const SETTING_KEY = 'sslcommerz_enabled';

    public function show(): JsonResponse
    {
        $setting = Setting::query()
            ->where('key', self::SETTING_KEY)
            ->first();

        return response()->json([
            'data' => [
                'sslcommerz_enabled' => $setting
                    ? (bool) ($setting->value['enabled'] ?? false)
                    : true,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sslcommerz_enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['sslcommerz_enabled'];

        DB::transaction(function () use ($enabled) {
            Setting::query()->updateOrCreate(
                ['key' => self::SETTING_KEY],
                [
                    'value' => ['enabled' => $enabled],
                    'group' => 'payment',
                    'description' => 'Enable or disable SSLCommerz payments.',
                ]
            );
        });

        return response()->json([
            'message' => 'Payment settings updated successfully.',
            'data' => [
                'sslcommerz_enabled' => $enabled,
            ],
        ]);
    }
}
