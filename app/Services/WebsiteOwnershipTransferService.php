<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteOwnershipTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WebsiteOwnershipTransferService
{
    public function __construct(
        private readonly AgencyWebsiteLimitService $websiteLimits,
    ) {}

    public function transfer(Website $website, User $actor, string $recipientEmail): WebsiteOwnershipTransfer
    {
        if (! $actor->hasPlanCapability('ownership_transfer')) {
            throw ValidationException::withMessages([
                'recipient_email' => 'Your current plan does not include website ownership transfer.',
            ]);
        }

        if ((int) $website->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'recipient_email' => 'Only the website owner can transfer ownership.',
            ]);
        }

        $recipient = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($recipientEmail))])->first();
        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient_email' => 'No Cosmic account was found for that email address.',
            ]);
        }

        if ((int) $recipient->id === (int) $actor->id) {
            throw ValidationException::withMessages([
                'recipient_email' => 'This website already belongs to that account.',
            ]);
        }

        return DB::transaction(function () use ($website, $actor, $recipient) {
            $lockedWebsite = Website::query()->whereKey($website->id)->lockForUpdate()->firstOrFail();
            User::query()->whereIn('id', [$actor->id, $recipient->id])->orderBy('id')->lockForUpdate()->get();

            if ((int) $lockedWebsite->user_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'recipient_email' => 'Website ownership changed before this handoff completed. Refresh and try again.',
                ]);
            }

            if ($message = $this->websiteLimits->validationMessage($recipient)) {
                throw ValidationException::withMessages(['recipient_email' => "The recipient cannot accept this website. {$message}"]);
            }

            $fromWorkspaceId = $lockedWebsite->workspace_id;
            $toWorkspace = $recipient->ownedWorkspaces()->first();

            $lockedWebsite->forceFill([
                'user_id' => $recipient->id,
                'workspace_id' => $toWorkspace?->id,
                'domain' => null,
                'api_token' => null,
                'deployment_secret' => null,
                'deployment_verified_at' => null,
                'last_deployed_at' => null,
                'deployment_error' => null,
            ])->save();

            return WebsiteOwnershipTransfer::create([
                'website_id' => $lockedWebsite->id,
                'from_user_id' => $actor->id,
                'to_user_id' => $recipient->id,
                'initiated_by_user_id' => $actor->id,
                'from_workspace_id' => $fromWorkspaceId,
                'to_workspace_id' => $toWorkspace?->id,
                'recipient_email' => $recipient->email,
                'status' => 'completed',
                'metadata' => [
                    'website_name' => $lockedWebsite->name,
                    'deployment_credentials_cleared' => true,
                ],
                'completed_at' => now(),
            ]);
        }, 3);
    }
}
