<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Model;

use Magento\Framework\Exception\LocalizedException;
use Sk\CustomerExtend\Api\RuleRepositoryInterface;
use Sk\CustomerExtend\Model\ResourceModel\Rule as RuleResource;
use Sk\CustomerExtend\Model\ResourceModel\Rule\CollectionFactory;

class RuleRepository implements RuleRepositoryInterface
{
    public function __construct(
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getMinimumQty(int $customerGroupId): float
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_group_id', $customerGroupId);
        $collection->setPageSize(1);

        $rule = $collection->getFirstItem();
        if (!$rule->getId()) {
            return 0.0;
        }

        return (float)$rule->getData('minimum_qty');
    }

    public function saveMinimumQty(int $customerGroupId, float $minimumQty): void
    {
        if ($customerGroupId < 0) {
            throw new LocalizedException(__('Invalid customer group ID.'));
        }

        if ($minimumQty < 0) {
            throw new LocalizedException(__('Minimum quantity cannot be negative.'));
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_group_id', $customerGroupId);
        $collection->setPageSize(1);

        $rule = $collection->getFirstItem();
        if (!$rule->getId()) {
            $rule = $this->ruleFactory->create();
            $rule->setData('customer_group_id', $customerGroupId);
        }

        $rule->setData('minimum_qty', $minimumQty);
        $this->ruleResource->save($rule);
    }

    public function delete(int $customerGroupId): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_group_id', $customerGroupId);

        foreach ($collection as $rule) {
            $this->ruleResource->delete($rule);
        }
    }
}
