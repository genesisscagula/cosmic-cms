<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use App\Services\AccountDataService;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request, AccountDataService $accountData): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileSummary' => $accountData->profile($user),
            'notificationPreferences' => app(\App\Services\NotificationPreferenceService::class)->all($user),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = $request->user();
            $validated = $request->safe()->except(['avatar', 'remove_avatar']);

            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($request->boolean('remove_avatar') && $user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
                $user->avatar_path = null;
            }

            if ($request->hasFile('avatar')) {
                $newPath = $request->file('avatar')->store('avatars/'.$user->id, 'public');

                if ($user->avatar_path && $user->avatar_path !== $newPath) {
                    Storage::disk('public')->delete($user->avatar_path);
                }

                $user->avatar_path = $newPath;
            }

            $user->profile_completed_at = $user->profile_completed_at ?? now();
            $user->save();

            $defaultWebsiteId = data_get($user->profile_settings, 'default_website_id');
            $website = $defaultWebsiteId
                ? $user->websites()->find($defaultWebsiteId)
                : $user->websites()->oldest('id')->first();

            if ($website) {
                $website->forceFill([
                    'name' => $user->business_name ?: $website->name,
                    'industry' => $user->industry ?: $website->industry,
                    'location' => $user->location,
                    'contact_email' => $user->email,
                    'contact_phone' => $user->phone,
                    'timezone' => $user->timezone,
                    'locale' => $user->locale,
                    'settings' => array_merge($website->settings ?? [], [
                        'business_name' => $user->business_name ?: $website->name,
                        'business_email' => $user->email,
                        'business_phone' => $user->phone,
                        'industry' => $user->industry,
                        'location' => $user->location,
                        'timezone' => $user->timezone,
                        'locale' => $user->locale,
                    ]),
                ])->save();
            }
        });

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->deleteDirectory('avatars/'.$user->id);
        }

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
