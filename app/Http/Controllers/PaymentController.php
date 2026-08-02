<?php

namespace App\Http\Controllers;

use App\Services\PaymentCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function checkout(Request $request, PaymentCheckoutService $checkout): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paymongo'])],
            'product_type' => ['required', Rule::in(['credits', 'plan'])],
            'product_key' => ['required', 'string', 'max:40'],
        ]);

        return response()->json($checkout->create(
            $request->user(),
            $validated['provider'],
            $validated['product_type'],
            $validated['product_key'],
        ));
    }

    public function success(Request $request)
    {
        return redirect()->route('credits.index')->with('status', 'Payment received. Credits activate after the verified webhook arrives.');
    }
}
