<?php

namespace App\Services;

/**
 * Central model-role resolver for Luna's three AI departments.
 *
 * Luna = customer-facing chat + lightweight routing.
 * Terra = normal registered-Spark / schema action work.
 * Sol   = complex/custom composition work (AI Flex in Batch 4).
 *
 * Explicit legacy per-stage env vars remain higher-priority so existing
 * deployments can override one stage without changing this department map.
 */
final class LunaModelDepartmentService
{
    public function luna(): string
    {
        return (string) config('openai.department_models.luna', env('OPENAI_MODEL_LUNA', env('OPENAI_MODEL3', env('OPENAI_MODEL', 'gpt-5-mini'))));
    }

    public function terra(): string
    {
        return (string) config('openai.department_models.terra', env('OPENAI_MODEL_TERRA', env('OPENAI_MODEL2', $this->luna())));
    }

    public function sol(): string
    {
        return (string) config('openai.department_models.sol', env('OPENAI_MODEL_SOL', env('OPENAI_MODEL1', $this->terra())));
    }

    public function router(): string
    {
        return (string) env('OPENAI_LUNA_ROUTER_MODEL', $this->luna());
    }

    public function scopeRouter(): string
    {
        return (string) env('OPENAI_LUNA_SCOPE_ROUTER_MODEL', $this->router());
    }

    public function sparkActionRouter(): string
    {
        return (string) env('OPENAI_LUNA_SPARK_ACTION_ROUTER_MODEL', env('OPENAI_LUNA_ACTION_ROUTER_MODEL', $this->router()));
    }

    public function sparkTargetRouter(): string
    {
        return (string) env('OPENAI_LUNA_SPARK_ROUTER_MODEL', $this->router());
    }

    public function actionRouter(): string
    {
        return (string) env('OPENAI_LUNA_ACTION_ROUTER_MODEL', $this->router());
    }

    public function domainRouter(): string
    {
        return (string) env('OPENAI_LUNA_DOMAIN_ROUTER_MODEL', env('OPENAI_LUNA_ACTION_MODEL', $this->terra()));
    }

    public function chat(): string
    {
        return (string) env('OPENAI_LUNA_RESPONSE_MODEL', $this->luna());
    }

    public function sparkEditor(): string
    {
        return (string) env('OPENAI_LUNA_SPARK_EDITOR_MODEL', $this->terra());
    }

    public function forSparkAction(?string $sparkAction): string
    {
        return $sparkAction === 'custom_spark' ? $this->sol() : $this->terra();
    }

    public function departmentForSparkAction(?string $sparkAction): string
    {
        return $sparkAction === 'custom_spark' ? 'sol' : 'terra';
    }

    /** @return array{luna:string,terra:string,sol:string} */
    public function map(): array
    {
        return [
            'luna' => $this->luna(),
            'terra' => $this->terra(),
            'sol' => $this->sol(),
        ];
    }
}
