<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\SendWorkspaceInvitationMailJob;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\AgencyPlanEntitlementService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberController extends Controller
{
    public function store(Request $request, AgencyPlanEntitlementService $entitlements)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,editor'],
            'website_ids' => ['nullable', 'array'],
            'website_ids.*' => ['integer', 'distinct'],
        ]);
        $workspace = $this->ownedWorkspace($request);
        $email = strtolower(trim($data['email']));
        $websiteIds = $workspace->websites()->whereIn('id', $data['website_ids'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($websiteIds) !== count($data['website_ids'] ?? [])) {
            throw ValidationException::withMessages(['website_ids' => 'One or more selected websites do not belong to this workspace.']);
        }

        if (strtolower($request->user()->email) === $email) {
            throw ValidationException::withMessages(['email' => 'You are already the workspace owner.']);
        }

        $currentMembers = max(0, $workspace->users()->count() - 1) + $workspace->invitations()->where('status', 'pending')->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();
        $decision = $entitlements->canAddTeamMember($request->user(), $currentMembers);
        if (! $decision->allowed) {
            throw ValidationException::withMessages(['email' => $decision->upgradeMessage ?: ($decision->reason ?: 'Your plan team member limit has been reached.')]);
        }

        if ($workspace->users()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already a workspace member.']);
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        DB::transaction(function () use ($workspace, $request, $email, $data, $websiteIds, $existing) {
            if ($existing) {
                $workspace->users()->syncWithoutDetaching([$existing->id => ['role' => $data['role']]]);
                foreach ($workspace->websites as $website) {
                    $website->assignedUsers()->detach($existing->id);
                }
                foreach ($websiteIds as $websiteId) {
                    $workspace->websites()->whereKey($websiteId)->firstOrFail()->assignedUsers()->syncWithoutDetaching([
                        $existing->id => ['assigned_by_user_id' => $request->user()->id, 'role' => $data['role'] === 'admin' ? 'website_admin' : 'website_editor'],
                    ]);
                }
                WorkspaceInvitation::query()->where('workspace_id', $workspace->id)->where('email', $email)->delete();
                return;
            }

            WorkspaceInvitation::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'email' => $email],
                ['invited_by_user_id' => $request->user()->id, 'name' => $data['name'] ?? null, 'role' => $data['role'], 'website_ids' => $websiteIds, 'token' => hash('sha256', Str::random(64)), 'status' => 'pending', 'expires_at' => now()->addDays(7)]
            );
        });

        if ($existing) {
            SendWorkspaceInvitationMailJob::dispatch(
                $email,
                'You were added to a Cosmic workspace',
                'Workspace access ready',
                'You now have access to '.$workspace->name.' as '.$data['role'].'. You can sign in with your existing account. If you forgot your password, use the password reset link on the sign-in screen.',
                'Open Cosmic CMS',
                route('dashboard'),
                $existing->id,
            );
        } else {
            $invitation = WorkspaceInvitation::query()->where('workspace_id', $workspace->id)->where('email', $email)->first();
            if ($invitation) {
                SendWorkspaceInvitationMailJob::dispatch(
                    $email,
                    'You were invited to a Cosmic workspace',
                    'Workspace invitation',
                    'You have been invited to join '.$workspace->name.' as '.$data['role'].'. Open the secure invitation to create your password and activate your account. The invitation expires in seven days.',
                    'Accept invitation',
                    route('workspace-invitations.show', $invitation->token),
                );
            }
        }

        return back()->with('success', $existing ? 'Team member added and notified by email.' : 'Team invitation sent by email.');
    }

    public function updateRole(Request $request, User $member, WorkspacePermissionService $permissions)
    {
        $workspace = $this->ownedWorkspace($request);
        if ((int) $workspace->owner_user_id === (int) $member->id) {
            throw ValidationException::withMessages(['role' => 'The workspace owner role cannot be changed.']);
        }

        $data = $request->validate(['role' => ['required', 'string', 'in:admin,editor']]);
        abort_unless($workspace->users()->whereKey($member->id)->exists(), 404);
        abort_unless($permissions->roleExists($data['role']), 422);

        $workspace->users()->updateExistingPivot($member->id, ['role' => $data['role']]);

        $websiteRole = $data['role'] === 'admin' ? 'website_admin' : 'website_editor';
        foreach ($workspace->websites as $website) {
            if ($website->assignedUsers()->whereKey($member->id)->exists()) {
                $website->assignedUsers()->updateExistingPivot($member->id, ['role' => $websiteRole]);
            }
        }

        return back()->with('success', 'Team member role updated.');
    }

    public function destroy(Request $request, User $member)
    {
        $workspace = $this->ownedWorkspace($request);
        if ((int) $workspace->owner_user_id === (int) $member->id) {
            throw ValidationException::withMessages(['member' => 'The workspace owner cannot be removed.']);
        }
        DB::transaction(function () use ($workspace, $member) {
            foreach ($workspace->websites as $website) {
                $website->assignedUsers()->detach($member->id);
            }
            $workspace->users()->detach($member->id);
        });

        return back()->with('success', 'Team member removed and website access revoked.');
    }


    public function resend(Request $request, WorkspaceInvitation $invitation)
    {
        $workspace = $this->ownedWorkspace($request);
        abort_unless((int) $invitation->workspace_id === (int) $workspace->id, 404);
        abort_unless($invitation->status === 'pending', 422, 'Only pending invitations can be resent.');

        $invitation->forceFill([
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
        ])->save();

        SendWorkspaceInvitationMailJob::dispatch(
            $invitation->email,
            'Your Cosmic workspace invitation was refreshed',
            'Invitation refreshed',
            'Your workspace invitation is available for another seven days. Open the secure invitation to create your password and activate your account.',
            'Accept invitation',
            route('workspace-invitations.show', $invitation->token),
        );
        return back()->with('success', 'Invitation refreshed and emailed for another seven days.');
    }

    public function cancel(Request $request, WorkspaceInvitation $invitation)
    {
        $workspace = $this->ownedWorkspace($request);
        abort_unless((int) $invitation->workspace_id === (int) $workspace->id, 404);
        $invitation->delete();
        return back()->with('success', 'Invitation cancelled.');
    }

    private function ownedWorkspace(Request $request): Workspace
    {
        $workspace = Workspace::query()->where('owner_user_id', $request->user()->id)->first();
        abort_unless($workspace, 403, 'Only a workspace owner can manage team members.');
        return $workspace;
    }
}
