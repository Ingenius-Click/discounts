<?php

namespace Ingenius\Discounts\Extensions;

use Ingenius\Orders\Extensions\BaseOrderExtension;
use Ingenius\Orders\Models\Order;
use Ingenius\Discounts\Models\DiscountUsage;
use Ingenius\Discounts\Models\DiscountCampaign;
use Ingenius\Discounts\Services\DiscountApplicationService;
use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\Enums\DiscountScope;

class DiscountExtensionForOrderCreation extends BaseOrderExtension
{
    public function __construct(
        protected DiscountApplicationService $discountApplicationService
    ) {}

    /**
     * Check if the calculate-discounts feature is enabled for the current tenant
     */
    protected function isFeatureEnabled(): bool
    {
        $tenant = tenant();
        return $tenant && $tenant->hasFeature('calculate-discounts');
    }

    /**
     * Process order using pre-calculated discounts from context
     *
     * This extension does NOT recalculate discounts - it uses the discounts
     * that were already calculated when the cart was displayed. This ensures
     * consistency between what the customer saw and what gets recorded.
     *
     * Product discounts are already reflected in items_subtotal (via getFinalPrice).
     * Cart discounts need to be subtracted from context['total'].
     * Shipping discounts are calculated fresh and passed to ShipmentExtension via context.
     */
    public function processOrder(Order $order, array $validatedData, array &$context): array
    {
        // Skip discount processing if feature is disabled
        if (!$this->isFeatureEnabled()) {
            return [
                'discounts_applied' => [],
                'total_cart_discount' => 0,
            ];
        }
        $discounts = $context['discounts'] ?? [
            'product_discounts' => [],
            'cart_discounts' => [],
        ];
        $appliedDiscounts = [];
        $totalCartDiscount = 0;

        // Register product discount usages (discounts already applied to items_subtotal)
        foreach ($discounts['product_discounts'] as $discount) {
            $this->registerDiscountUsage($order, $discount);

            $appliedDiscounts[] = [
                'campaign_id' => $discount['campaign_id'],
                'campaign_name' => $discount['campaign_name'],
                'discount_type' => $discount['discount_type'],
                'amount_saved' => $discount['amount_saved'],
                'scope' => 'product',
            ];
        }

        // Register cart discount usages and subtract from total
        foreach ($discounts['cart_discounts'] as $cartDiscount) {
            $amountSaved = $cartDiscount['amount_saved'] ?? 0;
            $totalCartDiscount += $amountSaved;

            $this->registerDiscountUsage($order, [
                'campaign_id' => $cartDiscount['campaign_id'],
                'campaign_name' => $cartDiscount['campaign_name'] ?? 'Cart Discount',
                'discount_type' => $cartDiscount['discount_type'],
                'amount_saved' => $amountSaved,
            ]);

            $appliedDiscounts[] = [
                'campaign_id' => $cartDiscount['campaign_id'],
                'campaign_name' => $cartDiscount['campaign_name'] ?? 'Cart Discount',
                'discount_type' => $cartDiscount['discount_type'],
                'amount_saved' => $amountSaved,
                'scope' => 'cart',
            ];
        }

        // Calculate shipping discounts fresh from the service
        // These need to be calculated at order time, not pre-loaded from cart
        $shippingDiscounts = $this->calculateShippingDiscounts($order);
        if (!empty($shippingDiscounts)) {
            $context['shipping_discounts'] = $shippingDiscounts;
        }

        // Subtract cart discounts from the total
        $context['total'] -= $totalCartDiscount;
        $context['total_cart_discount'] = $totalCartDiscount;

        return [
            'discounts_applied' => $appliedDiscounts,
            'total_cart_discount' => $totalCartDiscount,
        ];
    }

    /**
     * Calculate shipping discounts using the discount application service
     *
     * @return array Shipping discounts with campaign info and discount values
     */
    protected function calculateShippingDiscounts(Order $order): array
    {
        $discountContext = DiscountContext::fromOrder($order);

        // Apply only shipping discounts
        $results = $this->discountApplicationService->applyDiscounts($discountContext, DiscountScope::SHIPPING);

        $shippingDiscounts = [];
        foreach ($results as $result) {
            if (!empty($result->metadata['shipping_discount'])) {
                $shippingDiscounts[] = [
                    'campaign_id' => $result->campaignId,
                    'campaign_name' => $result->campaignName,
                    'discount_type' => $result->metadata['discount_type'] ?? $result->discountType,
                    'discount_value' => $result->metadata['discount_value'] ?? 0,
                ];
            }
        }

        return $shippingDiscounts;
    }

    /**
     * Register a discount usage record and increment campaign counter
     */
    protected function registerDiscountUsage(Order $order, array $discount): void
    {
        // Build affected items info for product-level discounts
        $affectedItems = [];
        if (!empty($discount['productible_id']) && !empty($discount['productible_type'])) {
            $affectedItems[] = [
                'productible_id' => $discount['productible_id'],
                'productible_type' => $discount['productible_type'],
                'quantity' => $discount['quantity'] ?? 1,
            ];
        }

        DiscountUsage::create([
            'campaign_id' => $discount['campaign_id'],
            'customer_id' => $order->userable_id,
            'orderable_id' => $order->id,
            'orderable_type' => get_class($order),
            'discount_amount_applied' => $discount['amount_saved'],
            'used_at' => now(),
            'metadata' => [
                'campaign_name' => $discount['campaign_name'],
                'discount_type' => $discount['discount_type'],
                'affected_items' => $affectedItems,
            ],
        ]);

        // Increment campaign usage counter
        $campaign = DiscountCampaign::find($discount['campaign_id']);
        if ($campaign) {
            $campaign->increment('current_uses');
        }
    }

    /**
     * No longer needed - cart discounts are handled in processOrder
     * Product discounts are already in items_subtotal
     */
    public function calculateSubtotal(Order $order, float $currentSubtotal, array &$context): float
    {
        return $currentSubtotal;
    }

    /**
     * Add discount information to order array
     */
    public function extendOrderArray(Order $order, array $orderArray): array
    {

        if(!$this->isFeatureEnabled()) {
            return $orderArray;
        }

        $usages = DiscountUsage::query()
            ->where('orderable_id', $order->id)
            ->where('orderable_type', get_class($order))
            ->get();

        $items = $usages->map(fn($usage) => [
            'campaign_id' => $usage->campaign_id,
            'name' => $usage->metadata['campaign_name'] ?? 'Unknown',
            'type' => $usage->metadata['discount_type'] ?? 'unknown',
            'amount_saved' => $usage->discount_amount_applied,
            'affected_items' => $usage->metadata['affected_items'] ?? [],
        ])->toArray();

        $orderArray['discounts'] = [
            'items' => $items,
            'total_amount' => $usages->sum('discount_amount_applied'),
        ];

        return $orderArray;
    }

    /**
     * Run before ShipmentExtension (priority 95)
     */
    public function getPriority(): int
    {
        return 10;
    }

    public function getName(): string
    {
        return 'DiscountProcessor';
    }
}