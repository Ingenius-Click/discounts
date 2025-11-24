<?php

namespace Ingenius\Discounts\DataTransferObjects;

/**
 * DiscountContext - Agnostic data structure for discount evaluation
 * Works both for pre-order (cart) and post-order scenarios
 */
class DiscountContext
{
    public function __construct(
        public readonly int $cartTotal,              // Total cart value in cents
        public readonly array $items,                // Array of cart/order items
        public readonly ?int $customerId,            // Customer ID (null for guests)
        public readonly ?string $customerType,       // Customer type (for polymorphic)
        public readonly array $requestData,          // Additional request data (shipping info, etc.)
        public readonly ?object $orderableEntity,    // The order entity if it exists (optional)
    ) {}

    /**
     * Create from cart (before order creation)
     */
    public static function fromCart(
        int $cartTotal,
        array $cartItems,
        ?int $customerId = null,
        ?string $customerType = null,
        array $requestData = []
    ): self {
        return new self(
            cartTotal: $cartTotal,
            items: $cartItems,
            customerId: $customerId,
            customerType: $customerType,
            requestData: $requestData,
            orderableEntity: null,
        );
    }

    /**
     * Create from order (after order creation)
     */
    public static function fromOrder(
        object $order,
        array $requestData = []
    ): self {
        // Extract items from order
        $items = [];
        foreach ($order->products as $product) {
            $items[] = [
                'productible_id' => $product->productible_id,
                'productible_type' => $product->productible_type,
                'quantity' => $product->quantity,
                'base_price_per_unit_in_cents' => $product->base_price_per_unit_in_cents,
                'base_total_in_cents' => $product->base_total_in_cents,
            ];
        }

        return new self(
            cartTotal: $order->items_subtotal ?? 0,
            items: $items,
            customerId: $order->userable_id,
            customerType: $order->userable_type,
            requestData: $requestData,
            orderableEntity: $order,
        );
    }

    /**
     * Get total quantity of all items
     */
    public function getTotalQuantity(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    /**
     * Check if context has a specific product
     */
    public function hasProduct(int $productId): bool
    {
        foreach ($this->items as $item) {
            if ($item['productible_id'] === $productId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if context has any of the specified products
     */
    public function hasAnyProduct(array $productIds): bool
    {
        foreach ($this->items as $item) {
            if (in_array($item['productible_id'], $productIds)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get items by product IDs
     */
    public function getItemsByProductIds(array $productIds): array
    {
        return array_filter($this->items, function ($item) use ($productIds) {
            return in_array($item['productible_id'], $productIds);
        });
    }

    /**
     * Check if this is a pre-order context (cart-based)
     */
    public function isPreOrder(): bool
    {
        return $this->orderableEntity === null;
    }

    /**
     * Check if this is a post-order context (order-based)
     */
    public function isPostOrder(): bool
    {
        return $this->orderableEntity !== null;
    }
}
