<?php

namespace App\Http\Controllers;

use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Cosmic\Pricing\ThemePricingRegistry;
use Illuminate\Http\Request;

class CosmicPricingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'balance' => (int) $request->user()->credits,
            'actions' => ActionPricing::all(),
            'blocks' => BlockPricingRegistry::all(),
            'themes' => ThemePricingRegistry::all(),
            'unlocks' => $request->user()->cosmicUnlocks()
                ->get(['unlock_type', 'unlock_key', 'credits_paid'])
                ->groupBy('unlock_type')
                ->map(fn ($items) => $items->pluck('unlock_key')->values()),
        ]);
    }
}
