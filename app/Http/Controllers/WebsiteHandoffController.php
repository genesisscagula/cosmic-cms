<?php

namespace App\Http\Controllers;

use App\Models\WebsiteOwnershipTransfer;
use App\Services\WebsiteOwnershipTransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteHandoffController extends Controller
{
    public function show(Request $request, string $token)
    {
        $handoff = WebsiteOwnershipTransfer::query()
            ->with(['website:id,name,industry', 'fromUser:id,name,email', 'toUser:id,name,email'])
            ->where('token', $token)
            ->firstOrFail();

        return Inertia::render('WebsiteHandoffs/Show', [
            'handoff' => [
                'token' => $handoff->token,
                'status' => $handoff->status,
                'recipient_email' => $handoff->recipient_email,
                'expires_at' => $handoff->expires_at?->toIso8601String(),
                'website' => $handoff->website,
                'from_user' => $handoff->fromUser,
                'to_user' => $handoff->toUser,
                'can_accept' => $handoff->isAcceptableBy($request->user()),
            ],
        ]);
    }

    public function accept(Request $request, string $token, WebsiteOwnershipTransferService $service)
    {
        $handoff = WebsiteOwnershipTransfer::query()->where('token', $token)->firstOrFail();
        $service->accept($handoff, $request->user());

        return redirect()->route('dashboard', ['tab' => 'websites'], 303)
            ->with('success', 'Website handoff accepted. You are now the owner.');
    }

    public function cancel(Request $request, WebsiteOwnershipTransfer $handoff, WebsiteOwnershipTransferService $service)
    {
        $service->cancel($handoff, $request->user());

        return back(303)->with('success', 'Website handoff cancelled.');
    }
}
