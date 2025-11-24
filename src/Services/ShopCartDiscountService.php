<?php

namespace Ingenius\Discounts\Services;

use Ingenius\Core\Helpers\AuthHelper;
use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\Enums\DiscountScope;

class ShopCartDiscountService
{
    public function __construct(
        protected DiscountApplicationService $discountService
    ) {}

    /**
     * Check if the calculate-discounts feature is enabled for the current tenant
     */
    protected function isFeatureEnabled(): bool
    {
        $tenant = tenant();
        return $tenant && $tenant->hasFeature('calculate-discounts');
    }

    public function applyDiscountsToCart(array $data, array $context): array {
        if (!$this->isFeatureEnabled()) {
            return $data;
        }

        $shopCartClass = config('discounts.shop_cart_model');

        $shopCart = app($shopCartClass);

        $cartItems = $shopCart->getCartItems();

        if($cartItems->isEmpty()) {
            return $data;
        }

        // Get current user
        $user = AuthHelper::getUser();
        $userId = $user ? ($user->id ?? null) : null;

        $discountContext = DiscountContext::fromCart(
            cartTotal: $shopCart->calculateSubtotalWithNoCartDiscounts(),
            cartItems: $cartItems->map(function($item) {

                $finalPrice = $item->productible?->getFinalPrice() ?? 0;

                return [
                    'productible_id' => $item->productible_id,
                    'productible_type' => $item->productible_type,
                    'base_total_in_cents' => $finalPrice * $item->quantity,
                    'base_price_per_unit_in_cents' => $finalPrice,
                    'quantity' => $item->quantity,
                ];
            })->toArray(),
            customerId: $userId,
            customerType: $user ? get_class($user) : null,
            requestData: []
        );

        $discounts = $this->discountService->applyDiscounts($discountContext, DiscountScope::CART);

        if ($discounts->isEmpty()) {
            return $data;
        }


        return [
            ... $data,
            ... $discounts->map(function($discount) {
                return [
                    'campaign_id' => $discount->campaignId,
                    'campaign_name' => $discount->campaignName,
                    'discount_type' => $discount->discountType,
                    'amount_saved' => $discount->amountSaved,
                    'affected_items' => $discount->affectedItems,
                ];
            })->toArray(),
        ];
    }
}