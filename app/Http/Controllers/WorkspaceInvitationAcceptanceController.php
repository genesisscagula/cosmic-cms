<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WorkspaceInvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = $this->pendingInvitation($token);
        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->exists();

        return Inertia::render('Client/AcceptInvitation', [
            'invitation' => [
                'token' => $invitation->token,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'workspace' => $invitation->workspace?->name,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'website_count' => count($invitation->website_ids ?? []),
                'existing_user' => $existingUser,
                'authenticated_email' => $request->user()?->email,
            ],
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->pendingInvitation($token);
        $email = strtolower($invitation->email);
        $user = $request->user();

        if ($user && strtolower($user->email) !== $email) {
            throw ValidationException::withMessages([
                'email' => 'This invitation belongs to '.$invitation->email.'. Sign out and use the invited account.',
            ]);
        }

        if (! $user) {
            $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existing) {
                throw ValidationException::withMessages([
                    'email' => 'An account already exists for this email. Sign in first, then reopen the invitation link.',
                ]);
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $user = new User([
                'name' => $data['name'],
                'email' => $email,
                'password' => Hash::make($data['password']),
                'account_type' => 'customer',
                'onboarding_status' => 'complete',
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        DB::transaction(function () use ($invitation, $user) {
            $invitation->workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role],
            ]);

            $websiteIds = $invitation->workspace->websites()
                ->whereIn('id', $invitation->website_ids ?? [])
                ->pluck('id');

            foreach ($websiteIds as $websiteId) {
                $invitation->workspace->websites()->whereKey($websiteId)->firstOrFail()
                    ->assignedUsers()->syncWithoutDetaching([
                        $user->id => ['assigned_by_user_id' => $invitation->invited_by_user_id, 'role' => $invitation->role === 'admin' ? 'website_admin' : 'website_editor'],
                    ]);
            }

            $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
        });

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Invitation accepted.');
    }

    private function pendingInvitation(string $token): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()->with('workspace')->where('token', $token)->firstOrFail();

        abort_if($invitation->status !== 'pending', 410, 'This invitation is no longer available.');
        abort_if($invitation->expires_at && $invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        return $invitation;
    }
}
