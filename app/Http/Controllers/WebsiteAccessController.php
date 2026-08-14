<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteAccessController extends Controller
{
    public function index(Request $request, Website $website)
    {
        $this->assertOwner($request, $website);

        $members = $website->assignedUsers()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role ?: 'website_editor',
            ])
            ->values();

        return response()->json(['members' => $members]);
    }

    public function store(Request $request, Website $website)
    {
        $this->assertOwner($request, $website);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::in(['website_admin', 'website_editor'])],
        ]);

        $member = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();
        if (! $member) {
            throw ValidationException::withMessages(['email' => 'No Cosmic account was found for that email address.']);
        }
        if ((int) $member->id === (int) $website->user_id) {
            throw ValidationException::withMessages(['email' => 'The website owner already has full access.']);
        }

        $website->assignedUsers()->syncWithoutDetaching([
            $member->id => [
                'assigned_by_user_id' => $request->user()->id,
                'role' => $data['role'],
            ],
        ]);
        $website->assignedUsers()->updateExistingPivot($member->id, [
            'assigned_by_user_id' => $request->user()->id,
            'role' => $data['role'],
        ]);

        return response()->json(['message' => 'Website access updated.']);
    }

    public function update(Request $request, Website $website, User $member)
    {
        $this->assertOwner($request, $website);
        abort_unless($website->assignedUsers()->whereKey($member->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(['website_admin', 'website_editor'])],
        ]);

        $website->assignedUsers()->updateExistingPivot($member->id, ['role' => $data['role']]);

        return response()->json(['message' => 'Website role updated.']);
    }

    public function destroy(Request $request, Website $website, User $member)
    {
        $this->assertOwner($request, $website);
        $website->assignedUsers()->detach($member->id);

        return response()->json(['message' => 'Website access removed.']);
    }

    private function assertOwner(Request $request, Website $website): void
    {
        abort_unless((int) $website->user_id === (int) $request->user()->id, 403, 'Only the website owner can manage access.');
    }
}
