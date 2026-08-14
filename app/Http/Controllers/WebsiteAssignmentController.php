<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteAssignmentController extends Controller
{
    public function update(Request $request, User $member, WorkspacePermissionService $permissions)
    {
        $workspace = Workspace::query()
            ->where('owner_user_id', $request->user()->id)
            ->first();

        abort_unless($workspace, 403, 'Only a workspace owner can assign websites.');
        abort_unless($workspace->users()->whereKey($member->id)->exists(), 404);

        if ((int) $workspace->owner_user_id === (int) $member->id) {
            throw ValidationException::withMessages([
                'websites' => 'The workspace owner always has access to every website.',
            ]);
        }

        $role = $workspace->roleFor($member);
        abort_unless($role && $permissions->allowsRole($role, 'websites.view'), 422);

        $data = $request->validate([
            'website_ids' => ['present', 'array'],
            'website_ids.*' => ['integer', 'distinct'],
        ]);

        $validWebsiteIds = $workspace->websites()
            ->whereIn('id', $data['website_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validWebsiteIds) !== count($data['website_ids'])) {
            throw ValidationException::withMessages([
                'website_ids' => 'One or more selected websites do not belong to this workspace.',
            ]);
        }

        DB::transaction(function () use ($workspace, $member, $validWebsiteIds, $request, $role) {
            $workspace->websites()->each(function ($website) use ($member) {
                $website->assignedUsers()->detach($member->id);
            });

            foreach ($validWebsiteIds as $websiteId) {
                $workspace->websites()->whereKey($websiteId)->firstOrFail()
                    ->assignedUsers()
                    ->attach($member->id, ['assigned_by_user_id' => $request->user()->id, 'role' => $role === 'admin' ? 'website_admin' : 'website_editor']);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Website assignments updated.',
                'website_ids' => $validWebsiteIds,
            ]);
        }

        return back()->with('success', 'Website assignments updated.');
    }
}
