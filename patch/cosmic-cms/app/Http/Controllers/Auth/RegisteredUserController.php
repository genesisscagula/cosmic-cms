<?php

namespace App\Http\Controllers\Auth;

use App\AI\Registries\IndustryMenuRegistry;
use App\Http\Controllers\Controller;
use App\Models\TrialGeneration;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\CreditService;
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

            $openingCredits = match ($trial?->selected_plan) {
                'growth' => 70,
                'pro' => 200,
                default => 30,
            };

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'account_type' => $trial ? 'client' : 'customer',
                'credits' => 0,
            ]);

            app(CreditService::class)->grant(
                $user,
                $openingCredits,
                $trial ? ucfirst((string) ($trial->selected_plan ?: 'starter')).' plan credits' : 'Welcome credits',
                $trial ? 'trial:'.$trial->token : 'registration:'.$user->id,
                ['plan' => $trial?->selected_plan ?: 'starter'],
            );

            if (! $trial) {
                $workspace = Workspace::create([
                    'owner_user_id' => $user->id,
                    'name' => $user->name.' Workspace',
                    'slug' => Str::slug($user->name.' Workspace').'-'.$user->id,
                ]);
                $workspace->users()->attach($user->id, ['role' => 'owner']);

                return [$user, null];
            }

            $menuStructure = $trial->menu_structure ?: IndustryMenuRegistry::for($trial->industry);

            $platformOwner = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower((string) config('cosmic.platform_owner_email'))])
                ->first();

            $workspace = $platformOwner?->ownedWorkspaces()->firstOrCreate(
                [],
                [
                    'name' => config('cosmic.agency_workspace_name'),
                    'slug' => Str::slug((string) config('cosmic.agency_workspace_name')).'-'.$platformOwner?->id,
                ]
            );

            if (! $workspace) {
                // Development-safe fallback when the configured platform owner
                // account has not been created yet.
                $workspace = Workspace::create([
                    'owner_user_id' => $user->id,
                    'name' => $user->name.' Workspace',
                    'slug' => Str::slug($user->name.' Workspace').'-'.$user->id,
                ]);
                $user->update(['account_type' => 'customer']);
                $workspaceRole = 'owner';
            } else {
                $workspaceRole = 'client';
            }

            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $workspaceRole],
            ]);

            $website = Website::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'name' => $trial->business_name,
                // This is a valid placeholder only. It never becomes a live deployment target.
                'domain' => 'https://'.(Str::slug($trial->business_name) ?: 'website').'.draft.cosmic.local',
                'industry' => $trial->industry,
                'location' => $trial->location,
                'business_description' => $trial->business_description,
                'contact_email' => $user->email,
                'api_token' => Str::random(60),
                'theme_settings' => $trial->preview_theme ?? [
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
                    'menu' => collect($menuStructure)
                        ->map(fn (array $menuPage) => [
                            'label' => $menuPage['title'],
                            'url' => ($menuPage['is_home'] ?? false) ? 'home' : $menuPage['slug'],
                        ])
                        ->values()
                        ->all(),
                ],
                'global_footer' => [
                    'type' => 'minimal_footer',
                    'logo_text' => $trial->business_name,
                    'copyright' => '© '.now()->year.'. All rights reserved.',
                ],
            ]);

            foreach ($menuStructure as $index => $menuPage) {
                $isHome = (bool) ($menuPage['is_home'] ?? $index === 0);

                $website->pages()->create([
                    'title' => $menuPage['title'],
                    'slug' => $menuPage['slug'],
                    'parent_id' => null,
                    'sort_order' => $menuPage['sort_order'] ?? ($index + 1),
                    'page_type' => $menuPage['page_type'] ?? 'standard',
                    // The purchased draft keeps the generated homepage only.
                    // All remaining industry pages start empty and ready to edit.
                    'blocks' => $isHome ? ($trial->generated_blocks ?? []) : [],
                    'status' => 'draft',
                ]);
            }

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
