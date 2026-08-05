<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppearancePreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'appearance' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $request->user()->forceFill([
            'appearance_preference' => $validated['appearance'],
        ])->save();

        return back()->with('status', 'appearance-updated');
    }
}
