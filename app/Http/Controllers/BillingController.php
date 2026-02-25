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
        return Inertia::render('BillingSuccess', [
            'credits' => $request->user()->credits,
        ]);
    }
}
