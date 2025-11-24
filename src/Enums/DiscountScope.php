<?php

namespace Ingenius\Discounts\Enums;

enum DiscountScope: string
{
    case PRODUCTS = 'products';   // Only product-level discounts
    case SHIPPING = 'shipping';   // Only shipping discounts
    case CART = 'cart';           // Only cart-level discounts
    case ALL = 'all';             // All discounts (default behavior)

    public function includesProducts(): bool
    {
        return $this === self::PRODUCTS || $this === self::ALL;
    }

    public function includesShipping(): bool
    {
        return $this === self::SHIPPING || $this === self::ALL;
    }

    public function includesCart(): bool
    {
        return $this === self::CART || $this === self::ALL;
    }
}
