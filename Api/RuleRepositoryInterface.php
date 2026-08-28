<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Api;

interface RuleRepositoryInterface
{
    public function getMinimumQty(int $customerGroupId): float;

    public function saveMinimumQty(int $customerGroupId, float $minimumQty): void;

    public function delete(int $customerGroupId): void;
}
