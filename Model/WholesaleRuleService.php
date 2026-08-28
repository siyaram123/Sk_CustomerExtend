<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Sk\CustomerExtend\Api\RuleRepositoryInterface;

class WholesaleRuleService
{
    public const WHOLESALE_ONLY_ATTRIBUTE = 'wholesale_only';
    public const GENERAL_GROUP_CODE = 'General';

    public function __construct(
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Check whether the product is Wholesale Only.
     */
    public function isWholesaleOnly(ProductInterface $product): bool
    {
        $value = $product->getData(self::WHOLESALE_ONLY_ATTRIBUTE);

        /*
         * The product object passed by Magento GraphQL may not contain
         * the custom EAV attribute. Explicitly load the product when
         * the attribute is missing.
         */
        if ($value === null && $product->getSku()) {
            try {
                $loadedProduct = $this->productRepository->get(
                    $product->getSku(),
                    false,
                    0,
                    true
                );

                $value = $loadedProduct->getData(
                    self::WHOLESALE_ONLY_ATTRIBUTE
                );
            } catch (NoSuchEntityException $e) {
                return false;
            }
        }

        return (bool)$value;
    }

    /**
     * Get minimum quantity for customer group.
     */
    public function getMinimumQty(
        ProductInterface $product,
        int $customerGroupId
    ): float {
        if (!$this->isWholesaleOnly($product)) {
            return 0.0;
        }

        return $this->ruleRepository->getMinimumQty($customerGroupId);
    }

    /**
     * Check whether customer group can purchase product.
     */
    public function isAllowedForCustomerGroup(
        ProductInterface $product,
        int $customerGroupId
    ): bool {
        if (!$this->isWholesaleOnly($product)) {
            return true;
        }

        // General customers cannot purchase Wholesale Only products.
        if ($this->isGeneralGroup($customerGroupId)) {
            return false;
        }

        // Other groups require a configured rule.
        return $this->ruleRepository->getMinimumQty($customerGroupId) > 0;
    }

    private function isGeneralGroup(int $customerGroupId): bool
    {
        try {
            return strcasecmp(
                (string)$this->groupRepository
                    ->getById($customerGroupId)
                    ->getCode(),
                self::GENERAL_GROUP_CODE
            ) === 0;
        } catch (NoSuchEntityException $e) {
            return $customerGroupId === CustomerContext::GENERAL_GROUP_ID;
        }
    }
}