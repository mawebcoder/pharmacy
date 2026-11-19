<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Http\Controllers;

use App\Modules\Shipping\Actions\RecordTipaxCallback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where Tipax posts the result of an order registration (roadmap S35): only with our secret in the address; the
 * payload is kept on the shipment it names.
 */
final class TipaxCallbackController
{
    public function __invoke(Request $request, string $result, RecordTipaxCallback $record): JsonResponse
    {
        $secret = (string) config('shipping.tipax.webhook_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->query('secret'))) {
            return response()->json(['ok' => false], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $found = $record($result === 'success', $payload);

        return response()->json(['ok' => $found]);
    }
}
