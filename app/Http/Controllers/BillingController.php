<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\IncompletePayment;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Billing', [
            'credits' => $user->credits,
        ]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user();

        try {
            $checkout = $user->checkoutCharge(200, 'How Does That Work? — 100 Credits', 1, [
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing'),
                'client_reference_id' => (string) $user->id,
            ]);

            return Inertia::location($checkout->url);
        } catch (IncompletePayment $e) {
            return redirect()->route('cashier.payment', [$e->payment->id, 'redirect' => route('billing')]);
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return redirect()->route('billing');
        }

        $user = $request->user();

        // Retrieve the Stripe checkout session to verify payment
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
        $session = $stripe->checkout->sessions->retrieve($sessionId);

        // Only credit if the payment was successful and hasn't already been applied
        $creditsAdded = 0;
        if ($session->payment_status === 'paid') {
            // Use client_reference_id to verify this session belongs to this user
            if ((int) $session->client_reference_id === $user->id) {
                // Prevent double-crediting by checking if we've already processed this session
                $alreadyProcessed = \Illuminate\Support\Facades\Cache::has("checkout_session_{$sessionId}");

                if (! $alreadyProcessed) {
                    $creditsAdded = 100;
                    $user->increment('credits', $creditsAdded);
                    $user->refresh();

                    // Mark this session as processed (cache for 24 hours)
                    \Illuminate\Support\Facades\Cache::put("checkout_session_{$sessionId}", true, now()->addHours(24));
                }
            }
        }

        return Inertia::render('BillingSuccess', [
            'credits' => $user->credits,
            'creditsAdded' => $creditsAdded ?: 100, // Show 100 even on revisit
        ]);
    }
}
