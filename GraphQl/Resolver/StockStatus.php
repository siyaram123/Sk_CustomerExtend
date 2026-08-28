<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sk\CustomerExtend\Model\StockVisibility;

class StockStatus implements ResolverInterface
{
    public function __construct(
        private readonly StockVisibility $stockVisibility
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
            return 'OUT_OF_STOCK';
        }

        return $this->stockVisibility->getStockStatus($product);
    }
}
