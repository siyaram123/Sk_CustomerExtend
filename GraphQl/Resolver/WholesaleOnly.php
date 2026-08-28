<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sk\CustomerExtend\Model\WholesaleRuleService;

class WholesaleOnly implements \Magento\Framework\GraphQl\Query\ResolverInterface
{
    public function __construct(
        private readonly WholesaleRuleService $ruleService
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $product = $value['model'] ?? null;
        if (!$product) {
            return false;
        }

        return $this->ruleService->isWholesaleOnly($product);
    }
}
