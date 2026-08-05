<?php

namespace App\Services;

/**
 * @deprecated Resolve App\Cosmic\Plans\PlanRegistry for new code.
 * This wrapper keeps existing service injections working during the V1 migration.
 */
class PlanRegistry extends \App\Cosmic\Plans\PlanRegistry
{
}
