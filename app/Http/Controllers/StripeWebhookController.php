<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $secret = config('cashier.webhook.secret');

        if ($secret) {
            $signature = $request->header('Stripe-Signature');

            try {
                $event = Webhook::constructEvent($payload, $signature, $secret);
            } catch (SignatureVerificationException $e) {
                return response()->json(['error' => 'Invalid signature.'], 400);
            }
        } else {
            $data = json_decode($payload, true);

            if (! is_array($data) || ! isset($data['type'])) {
                return response()->json(['error' => 'Invalid payload.'], 400);
            }

            $event = (object) [
                'type' => $data['type'],
                'data' => (object) ['object' => (object) ($data['data']['object'] ?? [])],
            ];
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($event->data->object);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $userId = $session->client_reference_id ?? null;

        if (! $userId) {
            Log::warning('Stripe webhook: checkout.session.completed missing client_reference_id');

            return;
        }

        $user = User::find($userId);

        if (! $user) {
            Log::warning('Stripe webhook: user not found', ['user_id' => $userId]);

            return;
        }

        $user->increment('credits', 100);
    }
}
