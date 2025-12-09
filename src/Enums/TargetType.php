<?php

namespace Ingenius\Discounts\Enums;

enum TargetType: string
{
    case PRODUCTS = 'products';
    case CATEGORIES = 'categories';
    case SHIPMENT = 'shipment';
    case SHOPCART = 'shopcart';

    /**
     * Get the fully qualified namespace for this target type
     */
    public function getNamespace(): string
    {
        return match ($this) {
            self::PRODUCTS => config('discounts.product_model'),
            self::CATEGORIES => config('discounts.category_model'),
            self::SHIPMENT => config('discounts.shipment_model'),
            self::SHOPCART => config('discounts.shop_cart_model'),
        };
    }

    /**
     * Create from namespace string
     */
    public static function fromNamespace(string $namespace): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getNamespace() === $namespace) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Get the string value of the enum
     */
    public function toString(): string
    {
        return $this->value;
    }
}
