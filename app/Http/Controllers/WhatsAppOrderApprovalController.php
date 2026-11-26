<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppOrder;
use App\Services\WhatsAppOrderDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppOrderApprovalController extends Controller
{
    public function authorizeOrder(
        Request $request,
        WhatsAppOrder $order,
        WhatsAppOrderDispatchService $dispatchService
    ): JsonResponse {
        $validated = $request->validate([
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'delivery_data' => ['nullable', 'array'],
        ]);

        try {
            $result = $dispatchService->authorizeAndDispatch(
                $order,
                (int) Auth::id(),
                $validated['payment_method_id'] ?? null,
                $validated['payment_reference'] ?? null,
                isset($validated['payment_amount']) ? (float) $validated['payment_amount'] : null,
                $validated['items'] ?? null,
                $validated['delivery_data'] ?? null
            );

            return response()->json([
                'ok' => true,
                'order_code' => $result['order']->order_code,
                'status' => $result['order']->status,
                'sale_id' => $result['sale']->id,
                'customer_notified' => $result['customer_notified'],
                'delivery_notified' => $result['delivery_notified'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
