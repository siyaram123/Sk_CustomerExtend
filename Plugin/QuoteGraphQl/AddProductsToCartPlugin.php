<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Plugin\QuoteGraphQl;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\AddProductsToCart;
use Sk\CustomerExtend\Model\CustomerContext;
use Sk\CustomerExtend\Model\WholesaleRuleService;

class AddProductsToCartPlugin
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerContext $customerContext,
        private readonly WholesaleRuleService $ruleService
    ) {
    }

    /**
     * Validate every item before Magento's native add-to-cart resolver runs.
     *
     * @param AddProductsToCart $subject
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws LocalizedException
     */
    public function beforeResolve(
        AddProductsToCart $subject,
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $args ??= [];
        $items = $args['cartItems'] ?? [];

        $groupId = $this->customerContext->getCustomerGroupId($context);

        foreach ($items as $item) {
            $sku = (string)($item['sku'] ?? '');
            $quantity = (float)($item['quantity'] ?? 0);

            if ($sku === '') {
                continue;
            }

            $product = $this->productRepository->get($sku, false, null, true);

            if (!$this->ruleService->isWholesaleOnly($product)) {
                continue;
            }

            // A wholesale-only product is available only to a group that
            // has an explicit minimum-quantity rule.
            if (!$this->ruleService->isAllowedForCustomerGroup($product, $groupId)) {
                throw new LocalizedException(
                    __('This product is not available for your account type.')
                );
            }

            $minimumQty = $this->ruleService->getMinimumQty($product, $groupId);

            if ($minimumQty > 0 && $quantity < $minimumQty) {
                throw new LocalizedException(
                    __(
                        'Minimum order quantity for %1 is %2.',
                        $product->getSku(),
                        rtrim(rtrim(number_format($minimumQty, 4, '.', ''), '0'), '.')
                    )
                );
            }
        }

        return [$field, $context, $info, $value, $args];
    }
}
