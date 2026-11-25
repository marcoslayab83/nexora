<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsAppWebhookService $service): JsonResponse
    {
        $configuredSecret = trim((string) Setting::getValue('evolution_webhook_secret', ''));
        if ($configuredSecret !== '') {
            $provided = (string) ($request->header('X-Webhook-Secret') ?: $request->query('secret', ''));
            if (! hash_equals($configuredSecret, $provided)) {
                return response()->json(['ok' => false, 'error' => 'Unauthorized webhook secret'], 401);
            }
        }

        $result = $service->handle($request->all());

        return response()->json(['ok' => true, ...$result]);
    }
}
