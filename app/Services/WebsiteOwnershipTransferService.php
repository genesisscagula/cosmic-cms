<?php

namespace App\Services;

use App\Models\User;
use App\Jobs\SendCosmicEventMailJob;
use App\Models\Website;
use App\Models\WebsiteOwnershipTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WebsiteOwnershipTransferService
{
    public function __construct(private readonly AgencyWebsiteLimitService $websiteLimits) {}

    public function transfer(Website $website, User $actor, string $recipientEmail): WebsiteOwnershipTransfer
    {
        $this->assertCanInitiate($website, $actor);
        $recipient = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($recipientEmail))])->first();

        if (! $recipient) throw ValidationException::withMessages(['recipient_email' => 'No Cosmic account was found for that email address.']);
        if ((int) $recipient->id === (int) $actor->id) throw ValidationException::withMessages(['recipient_email' => 'This website already belongs to that account.']);
        if ($message = $this->websiteLimits->validationMessage($recipient)) throw ValidationException::withMessages(['recipient_email' => "The recipient cannot accept this website. {$message}"]);

        WebsiteOwnershipTransfer::query()->where('website_id', $website->id)->where('status', 'pending')->update([
            'status' => 'cancelled', 'cancelled_at' => now(),
        ]);

        $handoff = WebsiteOwnershipTransfer::create([
            'website_id' => $website->id,
            'from_user_id' => $actor->id,
            'to_user_id' => $recipient->id,
            'initiated_by_user_id' => $actor->id,
            'from_workspace_id' => $website->workspace_id,
            'recipient_email' => $recipient->email,
            'token' => Str::random(64),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'metadata' => ['website_name' => $website->name],
        ]);
        SendCosmicEventMailJob::dispatch($recipient->id, 'handoff', 'Website handoff request', 'A website is ready for handoff', $actor->name.' wants to transfer '.$website->name.' to your account.', 'Review handoff', route('website-handoffs.show', $handoff->token));
        return $handoff;
    }

    public function accept(WebsiteOwnershipTransfer $handoff, User $recipient): WebsiteOwnershipTransfer
    {
        if (! $handoff->isAcceptableBy($recipient)) {
            throw ValidationException::withMessages(['handoff' => 'This handoff is expired, cancelled, completed, or belongs to another account.']);
        }

        return DB::transaction(function () use ($handoff, $recipient) {
            $locked = WebsiteOwnershipTransfer::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();
            $website = Website::query()->whereKey($locked->website_id)->lockForUpdate()->firstOrFail();
            if (! $locked->isAcceptableBy($recipient)) throw ValidationException::withMessages(['handoff' => 'This handoff is no longer available.']);
            if ((int) $website->user_id !== (int) $locked->from_user_id) throw ValidationException::withMessages(['handoff' => 'Website ownership changed before acceptance.']);
            if ($message = $this->websiteLimits->validationMessage($recipient)) throw ValidationException::withMessages(['handoff' => "Your account cannot accept this website. {$message}"]);

            $toWorkspace = $recipient->ownedWorkspaces()->first();
            $website->assignedUsers()->detach();
            $website->forceFill([
                'user_id' => $recipient->id, 'workspace_id' => $toWorkspace?->id, 'domain' => null,
                'api_token' => null, 'deployment_secret' => null, 'deployment_verified_at' => null,
                'last_deployed_at' => null, 'deployment_error' => null,
            ])->save();

            $locked->forceFill([
                'to_workspace_id' => $toWorkspace?->id,
                'status' => 'completed', 'accepted_at' => now(), 'completed_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], ['deployment_credentials_cleared' => true]),
            ])->save();

            return $locked;
        }, 3);
    }

    public function cancel(WebsiteOwnershipTransfer $handoff, User $actor): void
    {
        if ((int) $handoff->from_user_id !== (int) $actor->id || $handoff->status !== 'pending') abort(403);
        $handoff->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    private function assertCanInitiate(Website $website, User $actor): void
    {
        if (! $actor->hasPlanCapability('ownership_transfer')) throw ValidationException::withMessages(['recipient_email' => 'Your current plan does not include website ownership transfer.']);
        if ((int) $website->user_id !== (int) $actor->id) throw ValidationException::withMessages(['recipient_email' => 'Only the website owner can transfer ownership.']);
    }
}
