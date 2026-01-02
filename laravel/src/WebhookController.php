<?php

namespace MandiriQris\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $client = app('mandiri-qris');
        
        try {
            $payload = $client->handleWebhook(
                $request->getContent(),
                $request->header('X-Signature', '')
            );
            
            // Dispatch event for application to handle
            event(new \MandiriQris\Laravel\Events\PaymentReceived($payload));
            
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            \Log::error('Mandiri QRIS webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
