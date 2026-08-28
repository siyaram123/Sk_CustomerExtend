<?php

declare(strict_types=1);

namespace Sk\CustomerExtend\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sk\CustomerExtend\Model\CustomerContext;

class CustomerGroupId implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContext $customerContext
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        return $this->customerContext->getCustomerGroupId($context);
    }
}