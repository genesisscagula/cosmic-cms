<?php

namespace App\Services;

final class SparkEditCapabilityExecutor
{
    public function __construct(
        private readonly SparkEditMutationValidator $validator,
        private readonly LunaExecutionVerificationService $verification,
    ) {
    }

    /**
     * Execute and strictly verify one relative capability mutation. The
     * returned operation records intentionally carry the canonical storage
     * path and final value so verification never has to infer CSS state.
     */
    public function executeRelative(
        array $blocks,
        int $index,
        string $property,
        string $direction,
        array $context = [],
    ): array {
        if (! isset($blocks[$index]) || ! is_array($blocks[$index])) {
            return ['applied' => false, 'blocks' => array_values($blocks), 'reason' => 'missing_target'];
        }

        $block = $blocks[$index];
        $target = (string) ($context['target'] ?? 'cards');
        $capability = $this->validator->validateMutation(
            (string) ($block['type'] ?? ''),
            $block,
            $target,
            $property,
        );
        if (! ($capability['valid'] ?? false)) {
            return [
                'applied' => false,
                'blocks' => array_values($blocks),
                'reason' => $capability['reason'] ?? 'unsupported_capability',
            ];
        }

        $mutation = $this->validator->applyRelative($block, $property, $direction, $context);
        if (! ($mutation['applied'] ?? false)) {
            return [
                'applied' => false,
                'blocks' => array_values($blocks),
                'reason' => $mutation['reason'] ?? 'mutation_failed',
                'path' => $mutation['path'] ?? null,
            ];
        }

        $beforeBlocks = array_values($blocks);
        $afterBlocks = $beforeBlocks;
        $afterBlocks[$index] = $mutation['block'];
        $stateChanged = $beforeBlocks !== $afterBlocks;
        $normalizedTarget = [
            'type' => $target === 'card' ? 'item' : 'collection',
            'key' => 'section.'.$index.'.cards',
            'index' => $index,
            'field' => $mutation['path'],
        ];
        $operationId = 'spark_'.$index.'_'.str_replace('-', '_', $property);
        $expectedState = ['path' => $mutation['path'], 'value' => $mutation['after']];

        $plan = [
            'operation_id' => $operationId,
            'domain' => 'design',
            'leaf_operation' => $property,
            'operation' => 'update',
            'scope' => 'section',
            'target' => $normalizedTarget,
            'changes' => ['relative' => ['direction' => $mutation['direction']]],
            'expected_state' => $expectedState,
        ];
        $applied = [
            'operation_id' => $operationId,
            'action' => 'update',
            'operation' => 'update',
            'domain' => 'design',
            'leaf_operation' => $property,
            'scope' => 'section',
            'target' => $normalizedTarget,
            'direction' => $mutation['direction'],
            'path' => $mutation['path'],
            'render_token' => $mutation['render_token'] ?? null,
            'before_value' => $mutation['before'],
            'after_value' => $mutation['after'],
            'final_state' => $expectedState,
            'verified' => $stateChanged,
        ];
        $verification = $this->verification->verify([$plan], [$applied], $stateChanged);
        $complete = (bool) ($verification['can_claim_complete'] ?? false);

        return [
            'applied' => $complete,
            'blocks' => $complete ? $afterBlocks : $beforeBlocks,
            'before_blocks' => $beforeBlocks,
            'after_blocks' => $afterBlocks,
            'mutation' => $mutation,
            'planned_operations' => [$plan],
            'applied_operations' => $complete ? [$applied] : [],
            'verification' => $verification,
            'reason' => $complete ? null : 'verification_failed',
        ];
    }

    /** Execute and verify one finite semantic component mutation. */
    public function executeSet(
        array $blocks,
        int $index,
        string $property,
        mixed $value,
        array $context = [],
    ): array {
        if (! isset($blocks[$index]) || ! is_array($blocks[$index])) {
            return ['applied' => false, 'blocks' => array_values($blocks), 'reason' => 'missing_target'];
        }

        $block = $blocks[$index];
        $target = (string) ($context['target'] ?? 'cards');
        $capability = $this->validator->validateMutation(
            (string) ($block['type'] ?? ''),
            $block,
            $target,
            $property,
        );
        if (! ($capability['valid'] ?? false)) {
            return [
                'applied' => false,
                'blocks' => array_values($blocks),
                'reason' => $capability['reason'] ?? 'unsupported_capability',
            ];
        }

        $mutation = $this->validator->applySet($block, $property, $value, $context);
        if (! ($mutation['applied'] ?? false)) {
            return [
                'applied' => false,
                'blocks' => array_values($blocks),
                'reason' => $mutation['reason'] ?? 'mutation_failed',
            ];
        }

        $beforeBlocks = array_values($blocks);
        $afterBlocks = $beforeBlocks;
        $afterBlocks[$index] = $mutation['block'];
        $stateChanged = $beforeBlocks !== $afterBlocks;
        $normalizedTarget = [
            'type' => $target === 'card' ? 'item' : 'collection',
            'key' => 'section.'.$index.'.cards',
            'index' => $index,
            'field' => $mutation['path'],
        ];
        $operationId = 'spark_'.$index.'_'.str_replace('-', '_', $property);
        $expectedState = ['path' => $mutation['path'], 'value' => $mutation['after']];
        $plan = [
            'operation_id' => $operationId,
            'domain' => 'design',
            'leaf_operation' => $property,
            'operation' => 'update',
            'scope' => 'section',
            'target' => $normalizedTarget,
            'changes' => ['value' => $mutation['after']],
            'expected_state' => $expectedState,
        ];
        $applied = [
            'operation_id' => $operationId,
            'action' => 'update',
            'operation' => 'update',
            'domain' => 'design',
            'leaf_operation' => $property,
            'scope' => 'section',
            'target' => $normalizedTarget,
            'path' => $mutation['path'],
            'render_token' => $mutation['render_token'] ?? null,
            'before_value' => $mutation['before'],
            'after_value' => $mutation['after'],
            'final_state' => $expectedState,
            'verified' => $stateChanged,
        ];
        $verification = $this->verification->verify([$plan], [$applied], $stateChanged);
        $complete = (bool) ($verification['can_claim_complete'] ?? false);

        return [
            'applied' => $complete,
            'blocks' => $complete ? $afterBlocks : $beforeBlocks,
            'before_blocks' => $beforeBlocks,
            'after_blocks' => $afterBlocks,
            'mutation' => $mutation,
            'planned_operations' => [$plan],
            'applied_operations' => $complete ? [$applied] : [],
            'verification' => $verification,
            'reason' => $complete ? null : ($stateChanged ? 'verification_failed' : 'no_change'),
        ];
    }
}
