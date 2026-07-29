<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TrialGeneration;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $trial = null;

        if ($request->filled('trial')) {
            $trial = TrialGeneration::query()
                ->where('token', $request->string('trial'))
                ->where('status', 'ready')
                ->whereNull('claimed_at')
                ->first();
        }

        return Inertia::render('Auth/Register', [
            'trialToken' => $trial?->token,
            'trialEmail' => $trial?->email,
            'trialPlan' => $trial?->selected_plan,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'trial_token' => ['nullable', 'uuid'],
        ]);

        $claimedWebsite = DB::transaction(function () use ($request) {
            $trial = null;

            if ($request->filled('trial_token')) {
                $trial = TrialGeneration::query()
                    ->where('token', $request->string('trial_token'))
                    ->where('status', 'ready')
                    ->whereNull('claimed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $trial) {
                    throw ValidationException::withMessages([
                        'trial_token' => 'This draft is no longer available. Please generate a new one.',
                    ]);
                }

                if ($trial->email && Str::lower($trial->email) !== Str::lower($request->email)) {
                    throw ValidationException::withMessages([
                        'email' => 'Use the same email address used to create this draft.',
                    ]);
                }
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if (! $trial) {
                return [$user, null];
            }

            $website = $user->websites()->create([
                'name' => $trial->business_name,
                // This is a valid placeholder only. It never becomes a live deployment target.
                'domain' => 'https://'.(Str::slug($trial->business_name) ?: 'website').'.draft.cosmic.local',
                'industry' => $trial->industry,
                'location' => $trial->location,
                'business_description' => $trial->business_description,
                'contact_email' => $user->email,
                'api_token' => Str::random(60),
                'theme_settings' => [
                    'primary' => 'midnight',
                    'secondary' => 'white',
                    'tertiary' => 'stone',
                    'auto' => true,
                ],
                'global_header' => [
                    'type' => 'glassmorphism_header',
                    'logo_text' => $trial->business_name,
                    'cta_label' => 'Get Started',
                    'cta_url' => '#',
                    'menu' => [
                        ['label' => 'Home', 'url' => 'home'],
                        ['label' => 'About', 'url' => '#'],
                        ['label' => 'Services', 'url' => '#'],
                    ],
                ],
                'global_footer' => [
                    'type' => 'minimal_footer',
                    'logo_text' => $trial->business_name,
                    'copyright' => '© '.now()->year.'. All rights reserved.',
                ],
            ]);

            $website->pages()->create([
                'title' => 'Home',
                'slug' => 'home',
                'page_type' => 'standard',
                'blocks' => $trial->generated_blocks,
                'status' => 'draft',
            ]);

            $trial->update([
                'claimed_at' => now(),
                'claimed_by_user_id' => $user->id,
            ]);

            return [$user, $website];
        });

        [$user, $website] = $claimedWebsite;

        event(new Registered($user));

        Auth::login($user);

        return $website
            ? redirect()->route('pages.index', $website)
            : redirect(route('dashboard', absolute: false));
    }
}
