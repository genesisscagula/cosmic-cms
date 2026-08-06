<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class AppearancePreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'appearance' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        if (! Schema::hasColumn($request->user()->getTable(), 'appearance_preference')) {
            return back()->with('status', 'appearance-pending-migration');
        }

        $request->user()->forceFill([
            'appearance_preference' => $validated['appearance'],
        ])->save();

        return back()->with('status', 'appearance-updated');
    }
}
