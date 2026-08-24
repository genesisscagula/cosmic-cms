<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LunaPendingActionService
{
    public function isAffirmative(string $message): bool
    {
        $value=Str::lower(trim($message));
        if($value==='') return false;

        return (bool) preg_match(
            '/^(?:yes|yep|yeah|sure|ok|okay|proceed|continue|go|go ahead|do it|please proceed|please continue|please go ahead|sounds good|go for it|yes please|absolutely|confirmed?)[.! ]*$/i',
            $value
        );
    }

    public function isNegative(string $message): bool
    {
        $value=Str::lower(trim($message));
        if($value==='') return false;

        return (bool) preg_match(
            '/^(?:no|nope|cancel|stop|never mind|nevermind|don\'t|do not|not now)[.! ]*$/i',
            $value
        );
    }

    public function shouldConfirmLarge(string $message, string $scope='page'): bool
    {
        // Canonical Action Schema V5 removed the legacy large-creative proceed layer.
        // This compatibility method now gates destructive deletion only.
        $q=Str::lower(trim($message));
        if($q==='') return false;

        return Str::contains($q,[
            'delete','remove this page','delete page','delete website','remove website'
        ]);
    }

    public function put(string $actorKey, array $payload, int $ttlSeconds=1800): string
    {
        $token=(string)Str::uuid();
        $payload['token']=$token;
        $payload['created_at']=now()->toIso8601String();
        Cache::put($this->tokenKey($actorKey,$token),$payload,now()->addSeconds($ttlSeconds));
        Cache::put($this->latestKey($actorKey),$token,now()->addSeconds($ttlSeconds));
        return $token;
    }

    public function peekLatest(string $actorKey): ?array
    {
        $token=Cache::get($this->latestKey($actorKey));
        if(!is_string($token)||$token==='') return null;
        $payload=Cache::get($this->tokenKey($actorKey,$token));
        return is_array($payload)?$payload:null;
    }


    public function consume(string $actorKey,string $token): ?array
    {
        $token=trim($token);
        if($token==='') return null;

        $payload=Cache::pull($this->tokenKey($actorKey,$token));
        $latest=Cache::get($this->latestKey($actorKey));
        if(is_string($latest) && hash_equals($latest,$token)){
            Cache::forget($this->latestKey($actorKey));
        }
        return is_array($payload)?$payload:null;
    }

    public function consumeLatest(string $actorKey): ?array
    {
        $token=Cache::pull($this->latestKey($actorKey));
        if(!is_string($token)||$token==='') return null;
        $payload=Cache::pull($this->tokenKey($actorKey,$token));
        return is_array($payload)?$payload:null;
    }

    public function cancelLatest(string $actorKey): void
    {
        $token=Cache::pull($this->latestKey($actorKey));
        if(is_string($token)&&$token!=='') Cache::forget($this->tokenKey($actorKey,$token));
    }

    private function latestKey(string $actorKey): string
    {
        return 'luna-pending:latest:'.sha1($actorKey);
    }

    private function tokenKey(string $actorKey,string $token): string
    {
        return 'luna-pending:item:'.sha1($actorKey).':'.$token;
    }
}
