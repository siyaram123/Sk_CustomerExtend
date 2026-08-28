<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;

class StockVisibility
{
    public function __construct(
        private readonly CustomerContext $customerContext,
        private readonly WholesaleRuleService $ruleService,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver
    ) {
    }

    public function isWholesaleCustomer(ContextInterface $context, ProductInterface $product): bool
    {
        return $this->isAllowedWholesaleGroup(
            $this->customerContext->getCustomerGroupId($context),
            $product
        );
    }

    public function isAllowedWholesaleGroup(int $groupId, ProductInterface $product): bool
    {
        return $this->ruleService->isWholesaleOnly($product)
            && $this->ruleService->getMinimumQty($product, $groupId) > 0;
    }

    public function getSalableQty(ProductInterface $product): float
    {
        $stock = $this->stockResolver->execute(
            SalesChannelInterface::TYPE_WEBSITE,
            'base'
        );

        return (float)$this->getProductSalableQty->execute(
            (string)$product->getSku(),
            (int)$stock->getStockId()
        );
    }

    public function getStockStatus(ProductInterface $product): string
    {
        try {
            return $this->getSalableQty($product) > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK';
        } catch (\Throwable) {
            // If MSI is unavailable for a particular installation, fall back
            // to Magento's product availability flag rather than exposing qty.
            return $product->isAvailable() ? 'IN_STOCK' : 'OUT_OF_STOCK';
        }
    }
}
