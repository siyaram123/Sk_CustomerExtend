<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sk\CustomerExtend\Model\CustomerContext;
use Sk\CustomerExtend\Model\WholesaleRuleService;

class MinimumOrderQuantity implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContext $customerContext,
        private readonly WholesaleRuleService $ruleService
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $product = $value['model'] ?? null;

        if (!$product || !$this->ruleService->isWholesaleOnly($product)) {
            return 0.0;
        }

        return $this->ruleService->getMinimumQty(
            $product,
            $this->customerContext->getCustomerGroupId($context)
        );
    }
}