<?php

namespace Tests\Unit;

use App\Services\LunaExecutionVerificationService;
use Tests\TestCase;

class LunaExecutionVerificationV5Test extends TestCase
{
    public function test_v5_target_must_be_echoed_by_the_executor(): void
    {
        $result = app(LunaExecutionVerificationService::class)->verify([[
            'operation_id' => 'op_1',
            'operation' => 'update',
            'domain' => 'typography',
            'target' => ['type' => 'heading', 'key' => 'hero.h1'],
        ]], [[
            'operation_id' => 'op_1',
            'operation' => 'update',
            'domain' => 'typography',
            'verified' => true,
        ]], true);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['can_claim_complete']);
        $this->assertSame(5, $result['contract_version']);
    }

    public function test_v5_expected_final_state_must_match(): void
    {
        $planned = [[
            'operation_id' => 'op_1',
            'operation' => 'update',
            'domain' => 'typography',
            'target' => ['type' => 'heading', 'key' => 'hero.h1'],
            'expected_state' => ['value' => '48px'],
        ]];
        $actual = [[
            'operation_id' => 'op_1',
            'operation' => 'update',
            'domain' => 'typography',
            'target' => ['type' => 'heading', 'key' => 'hero.h1'],
            'final_state' => ['value' => '56px'],
            'verified' => true,
        ]];

        $result = app(LunaExecutionVerificationService::class)->verify($planned, $actual, true);
        $this->assertSame('failed', $result['status']);

        $actual[0]['final_state']['value'] = '48px';
        $result = app(LunaExecutionVerificationService::class)->verify($planned, $actual, true);
        $this->assertSame('complete', $result['status']);
        $this->assertTrue($result['can_claim_complete']);
    }

    public function test_v5_structural_replace_cannot_be_verified_by_a_plain_edit(): void
    {
        $result = app(LunaExecutionVerificationService::class)->verify([[
            'operation_id' => 'op_1',
            'operation' => 'replace',
            'domain' => 'section',
            'target' => ['type' => 'section', 'index' => 0],
        ]], [[
            'operation_id' => 'op_1',
            'operation' => 'edit',
            'domain' => 'section',
            'target' => ['type' => 'section', 'index' => 0],
            'verified' => true,
        ]], true);

        $this->assertSame('failed', $result['status']);
    }

    public function test_v5_relative_direction_must_match_the_verified_delta(): void
    {
        $planned = [[
            'operation_id' => 'op_1',
            'operation' => 'update',
            'domain' => 'typography',
            'leaf_operation' => 'font_size',
            'target' => ['type' => 'heading', 'key' => 'hero.h1'],
            'changes' => ['relative_size' => ['direction' => 'decrease']],
        ]];
        $actual = [[
            'operation_id' => 'op_1',
            'operation' => 'update',
            'domain' => 'typography',
            'leaf_operation' => 'font_size',
            'target' => ['type' => 'heading', 'key' => 'hero.h1'],
            'direction' => 'increase',
            'verified' => true,
        ]];

        $result = app(LunaExecutionVerificationService::class)->verify($planned, $actual, true);
        $this->assertSame('failed', $result['status']);

        $actual[0]['direction'] = 'decrease';
        $result = app(LunaExecutionVerificationService::class)->verify($planned, $actual, true);
        $this->assertSame('complete', $result['status']);
    }
}
