<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Credits/Index', [
            'balance' => (int) $user->credits,
            'transactions' => $user->creditTransactions()
                ->with('website:id,name')
                ->latest()
                ->paginate(20)
                ->through(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'website' => $transaction->website?->only(['id', 'name']),
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
