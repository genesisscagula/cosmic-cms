<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Collection;
use Throwable;

final class BulkWebsiteActionService
{
    public function __construct(
        private readonly AgencyWebsiteLimitService $websiteLimits,
        private readonly WebsiteDuplicationService $duplicator,
    ) {
    }

    /**
     * Execute a bulk action independently per website so one bad record does
     * not hide successful work on the remaining selection.
     */
    public function execute(User $user, Collection $websites, string $action): array
    {
        $workspace = $user->ownedWorkspaces()->first();
        $completed = [];
        $failed = [];

        foreach ($websites as $website) {
            try {
                match ($action) {
                    'duplicate' => $this->duplicate($user, $website, $workspace),
                    'disconnect' => $this->disconnect($website),
                    'delete' => $website->deleteOrFail(),
                    default => throw new \InvalidArgumentException('Unsupported bulk website action.'),
                };

                $completed[] = [
                    'id' => $website->id,
                    'name' => $website->name ?: 'Untitled Website',
                ];
            } catch (Throwable $exception) {
                report($exception);

                $failed[] = [
                    'id' => $website->id,
                    'name' => $website->name ?: 'Untitled Website',
                    'message' => $this->safeMessage($exception),
                ];
            }
        }

        return [
            'action' => $action,
            'requested' => $websites->count(),
            'completed_count' => count($completed),
            'failed_count' => count($failed),
            'completed' => $completed,
            'failed' => $failed,
        ];
    }

    private function duplicate(User $user, Website $website, $workspace): void
    {
        if ($message = $this->websiteLimits->validationMessage($user)) {
            throw new \RuntimeException($message);
        }

        $this->duplicator->duplicate($website, $user, $workspace);
    }

    private function disconnect(Website $website): void
    {
        $website->forceFill([
            'deployment_secret' => null,
            'deployment_verified_at' => null,
            'last_deployed_at' => null,
            'deployment_error' => null,
        ])->saveOrFail();
    }

    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof \RuntimeException || $exception instanceof \InvalidArgumentException) {
            return $exception->getMessage();
        }

        return 'This website could not be updated. Please try again.';
    }
}
