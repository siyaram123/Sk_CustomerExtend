<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class CustomerContext
{
    public const GENERAL_GROUP_ID = 1;

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * Get logged-in customer ID from Magento GraphQL context.
     *
     * Guests return null.
     */
    public function getCustomerId($context): ?int
    {
        $userId = $context->getUserId();

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * Get customer group ID.
     *
     * Guests are treated as General customers.
     */
    public function getCustomerGroupId($context): int
    {
        $customerId = $this->getCustomerId($context);

        if (!$customerId) {
            return self::GENERAL_GROUP_ID;
        }

        $customer = $this->customerRepository->getById($customerId);

        return (int) $customer->getGroupId();
    }

    /**
     * Get logged-in customer.
     *
     * Guests return null.
     */
    public function getCustomer($context): ?CustomerInterface
    {
        $customerId = $this->getCustomerId($context);

        if (!$customerId) {
            return null;
        }

        return $this->customerRepository->getById($customerId);
    }
}