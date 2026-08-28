<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sk\CustomerExtend\Model\CustomerContext;
use Sk\CustomerExtend\Model\StockVisibility;
use Sk\CustomerExtend\Model\WholesaleRuleService;

class StockQuantity implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContext $customerContext,
        private readonly WholesaleRuleService $ruleService,
        private readonly StockVisibility $stockVisibility
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?float {
        $product = $value['model'] ?? null;
        if (!$product || !$this->ruleService->isWholesaleOnly($product)) {
            return null;
        }

        $groupId = $this->customerContext->getCustomerGroupId($context);

        // Exact quantity is only exposed to a group having an explicit rule.
        if (!$this->ruleService->isAllowedForCustomerGroup($product, $groupId)) {
            return null;
        }

        return $this->stockVisibility->getSalableQty($product);
    }
}
